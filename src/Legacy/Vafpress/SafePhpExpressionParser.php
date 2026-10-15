<?php

declare(strict_types=1);

namespace Pressundo\Framework\Legacy\Vafpress;

final class SafePhpExpressionParser
{
    /** @var list<array{0:int,1:string}|string> */
    private array $tokens = [];
    private int $index = 0;
    /** @var list<string> */
    private array $warnings = [];
    /** @var array<string,mixed> */
    private array $variables = [];

    /** @param array<string,mixed> $variables */
    public function __construct(
        private readonly string $sourceFile = '',
        private readonly string $targetRoot = '',
        private readonly ?string $templateRoot = null,
        private readonly ?string $stylesheetRoot = null,
        private readonly ?string $templateUri = null,
        private readonly ?string $stylesheetUri = null,
        array $variables = []
    ) {
        $this->variables = $variables;
    }

    /** @return array{value:mixed,warnings:list<string>} */
    public function parse(string $expression): array
    {
        $this->tokens = $this->normalizeTokens(token_get_all("<?php " . $expression));
        $this->index = 0;
        $this->warnings = [];

        $value = $this->parseExpression();

        return ['value' => $value, 'warnings' => array_values(array_unique($this->warnings))];
    }

    /** @return array{value:mixed,warnings:list<string>} */
    public function parseReturnedArrayFile(string $file): array
    {
        $content = @file_get_contents($file);
        if (! is_string($content)) {
            return ['value' => null, 'warnings' => ['Template file could not be read.']];
        }

        $tokens = token_get_all($content);
        $start = null;
        $depth = 0;
        foreach ($tokens as $i => $token) {
            if (is_array($token) && $token[0] === T_RETURN && $depth === 0) {
                $start = $i;
                break;
            }
            if ($token === '{') {
                ++$depth;
            } elseif ($token === '}') {
                $depth = max(0, $depth - 1);
            }
        }

        if ($start === null) {
            return ['value' => null, 'warnings' => ['No top-level return expression was found in the template file.']];
        }

        // Resolve simple top-level assignments such as `$items = [...]` without
        // executing the template. This is common in real Vafpress templates and
        // keeps legacy option lists/images available to the compatibility runtime.
        $variables = $this->variables;
        $warnings = [];
        $depth = 0;
        for ($i = 0; $i < $start; ++$i) {
            $token = $tokens[$i];
            if ($token === '{') { ++$depth; continue; }
            if ($token === '}') { $depth = max(0, $depth - 1); continue; }
            if ($depth !== 0 || ! is_array($token) || $token[0] !== T_VARIABLE) {
                continue;
            }

            $name = ltrim($token[1], '$');
            $j = $i + 1;
            while ($j < $start && is_array($tokens[$j]) && in_array($tokens[$j][0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) { ++$j; }
            if (($tokens[$j] ?? null) !== '=') {
                continue;
            }
            ++$j;
            $expression = '';
            $paren = 0;
            $bracket = 0;
            for (; $j < $start; ++$j) {
                $part = $tokens[$j];
                $text = is_array($part) ? $part[1] : $part;
                if ($text === '(') { ++$paren; }
                elseif ($text === ')') { $paren = max(0, $paren - 1); }
                elseif ($text === '[') { ++$bracket; }
                elseif ($text === ']') { $bracket = max(0, $bracket - 1); }
                elseif ($text === ';' && $paren === 0 && $bracket === 0) { break; }
                $expression .= $text;
            }
            if (trim($expression) === '') {
                continue;
            }
            $assignmentParser = new self(
                $file,
                $this->targetRoot,
                $this->templateRoot,
                $this->stylesheetRoot,
                $this->templateUri,
                $this->stylesheetUri,
                $variables
            );
            $parsedAssignment = $assignmentParser->parse($expression);
            $variables[$name] = $parsedAssignment['value'];
            foreach ((array) ($parsedAssignment['warnings'] ?? []) as $warning) {
                $warnings[] = sprintf('$%s: %s', $name, $warning);
            }
            $i = $j;
        }

        $expression = '';
        $paren = 0;
        $bracket = 0;
        for ($i = $start + 1, $count = count($tokens); $i < $count; ++$i) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;
            if ($text === '(') {
                ++$paren;
            } elseif ($text === ')') {
                $paren = max(0, $paren - 1);
            } elseif ($text === '[') {
                ++$bracket;
            } elseif ($text === ']') {
                $bracket = max(0, $bracket - 1);
            } elseif ($text === ';' && $paren === 0 && $bracket === 0) {
                break;
            }
            $expression .= $text;
        }

        $parser = new self(
            $file,
            $this->targetRoot,
            $this->templateRoot,
            $this->stylesheetRoot,
            $this->templateUri,
            $this->stylesheetUri,
            $variables
        );
        $result = $parser->parse($expression);
        $result['warnings'] = array_values(array_unique(array_merge($warnings, (array) ($result['warnings'] ?? []))));
        return $result;
    }

    /** @param array<int,array{0:int,1:string,2?:int}|string> $tokens @return list<array{0:int,1:string}|string> */
    private function normalizeTokens(array $tokens): array
    {
        $normalized = [];
        foreach ($tokens as $token) {
            if (is_array($token)) {
                if (in_array($token[0], [T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    continue;
                }
                $normalized[] = [$token[0], $token[1]];
            } else {
                $normalized[] = $token;
            }
        }
        return $normalized;
    }

    private function parseExpression(): mixed
    {
        $value = $this->parsePrimary();
        while ($this->peekText() === '.') {
            $this->index++;
            $right = $this->parsePrimary();
            if (is_scalar($value) || $value === null) {
                $leftText = (string) ($value ?? '');
            } else {
                $leftText = '';
            }
            if (is_scalar($right) || $right === null) {
                $rightText = (string) ($right ?? '');
            } else {
                $rightText = '';
            }
            $value = $leftText . $rightText;
        }
        return $value;
    }

    private function parsePrimary(): mixed
    {
        $token = $this->current();
        if ($token === null) {
            return null;
        }

        if ($token === '[') {
            return $this->parseArray(']');
        }

        if ($token === '(') {
            ++$this->index;
            $value = $this->parseExpression();
            $this->consume(')');
            return $value;
        }

        if ($token === '-') {
            ++$this->index;
            $value = $this->parsePrimary();
            return is_numeric($value) ? -1 * $value : null;
        }

        if (is_array($token)) {
            [$id, $text] = $token;
            if ($id === T_VARIABLE) {
                ++$this->index;
                $name = ltrim($text, '$');
                if (array_key_exists($name, $this->variables)) {
                    return $this->variables[$name];
                }
                $this->warnings[] = sprintf('Unresolved variable "$%s".', $name);
                return null;
            }
            if ($id === T_ARRAY) {
                ++$this->index;
                if (! $this->consume('(')) {
                    $this->warnings[] = 'Malformed array() expression.';
                    return [];
                }
                return $this->parseArray(')');
            }
            if ($id === T_CONSTANT_ENCAPSED_STRING) {
                ++$this->index;
                return $this->decodeString($text);
            }
            if ($id === T_LNUMBER) {
                ++$this->index;
                return (int) $text;
            }
            if ($id === T_DNUMBER) {
                ++$this->index;
                return (float) $text;
            }
            if (defined('T_DIR') && $id === T_DIR) {
                ++$this->index;
                return $this->sourceFile !== '' ? dirname($this->sourceFile) : $this->targetRoot;
            }
            if (defined('T_FILE') && $id === T_FILE) {
                ++$this->index;
                return $this->sourceFile;
            }
            if (in_array($id, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                return $this->parseName($text);
            }
        }

        $this->warnings[] = 'Unsupported PHP expression near: ' . $this->tokenText($token);
        ++$this->index;
        return null;
    }

    private function parseName(string $name): mixed
    {
        ++$this->index;
        $lower = strtolower(ltrim($name, '\\'));
        if ($lower === 'true') {
            return true;
        }
        if ($lower === 'false') {
            return false;
        }
        if ($lower === 'null') {
            return null;
        }
        if ($lower === 'directory_separator') {
            return DIRECTORY_SEPARATOR;
        }
        if ($lower === 'wpalchemy_mode_array') {
            return 'array';
        }
        if ($lower === 'wpalchemy_mode_extract') {
            return 'extract';
        }

        if ($this->peekText() !== '(') {
            $this->warnings[] = sprintf('Unresolved constant "%s".', $name);
            return null;
        }

        ++$this->index;
        $args = [];
        while ($this->current() !== null && $this->peekText() !== ')') {
            $args[] = $this->parseExpression();
            if ($this->peekText() === ',') {
                ++$this->index;
                continue;
            }
            break;
        }
        $this->consume(')');

        return $this->evaluateFunction($lower, $args, $name);
    }

    /** @param list<mixed> $args */
    private function evaluateFunction(string $lower, array $args, string $originalName): mixed
    {
        if (in_array($lower, ['__', '_x', 'esc_html__', 'esc_attr__'], true)) {
            return isset($args[0]) && is_scalar($args[0]) ? (string) $args[0] : '';
        }
        if ($lower === 'dirname') {
            return isset($args[0]) && is_string($args[0]) ? dirname($args[0]) : '';
        }
        if ($lower === 'basename') {
            return isset($args[0]) && is_string($args[0]) ? basename($args[0]) : '';
        }
        if ($lower === 'trailingslashit') {
            return isset($args[0]) && is_string($args[0]) ? rtrim($args[0], '/\\') . '/' : '';
        }
        if ($lower === 'plugin_dir_path') {
            return isset($args[0]) && is_string($args[0]) ? rtrim(dirname($args[0]), '/\\') . '/' : '';
        }
        if ($lower === 'get_template_directory') {
            return $this->templateRoot ?? $this->targetRoot;
        }
        if ($lower === 'get_stylesheet_directory') {
            return $this->stylesheetRoot ?? $this->targetRoot;
        }
        if ($lower === 'get_template_directory_uri') {
            return $this->templateUri ?? '';
        }
        if ($lower === 'get_stylesheet_directory_uri') {
            return $this->stylesheetUri ?? $this->templateUri ?? '';
        }

        $this->warnings[] = sprintf('Function "%s" was not executed during safe parsing.', $originalName);
        return null;
    }

    /** @return array<mixed> */
    private function parseArray(string $closing): array
    {
        if ($this->peekText() === '[' || $this->peekText() === '(') {
            ++$this->index;
        }

        $result = [];
        $nextIndex = 0;

        while ($this->current() !== null && $this->peekText() !== $closing) {
            $first = $this->parseExpression();
            if ($this->peekText() === '=>') {
                ++$this->index;
                $value = $this->parseExpression();
                $key = is_int($first) || is_string($first) ? $first : $nextIndex;
                $result[$key] = $value;
                if (is_int($key) && $key >= $nextIndex) {
                    $nextIndex = $key + 1;
                }
            } else {
                $result[$nextIndex++] = $first;
            }

            if ($this->peekText() === ',') {
                ++$this->index;
                continue;
            }

            if ($this->peekText() !== $closing) {
                $this->recoverToDelimiter($closing);
                if ($this->peekText() === ',') {
                    ++$this->index;
                }
            }
        }

        $this->consume($closing);
        return $result;
    }

    private function recoverToDelimiter(string $closing): void
    {
        $depthParen = 0;
        $depthBracket = 0;
        while (($token = $this->current()) !== null) {
            $text = $this->tokenText($token);
            if ($text === '(') {
                ++$depthParen;
            } elseif ($text === ')' && $depthParen > 0) {
                --$depthParen;
            } elseif ($text === '[') {
                ++$depthBracket;
            } elseif ($text === ']' && $depthBracket > 0) {
                --$depthBracket;
            }

            if ($depthParen === 0 && $depthBracket === 0 && ($text === ',' || $text === $closing)) {
                return;
            }
            ++$this->index;
        }
    }

    private function consume(string $text): bool
    {
        if ($this->peekText() !== $text) {
            return false;
        }
        ++$this->index;
        return true;
    }

    /** @return array{0:int,1:string}|string|null */
    private function current(): array|string|null
    {
        return $this->tokens[$this->index] ?? null;
    }

    private function peekText(): ?string
    {
        $token = $this->current();
        return $token === null ? null : $this->tokenText($token);
    }

    /** @param array{0:int,1:string}|string $token */
    private function tokenText(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }

    private function decodeString(string $literal): string
    {
        $quote = $literal[0] ?? "'";
        $value = substr($literal, 1, -1);
        if ($quote === "'") {
            return str_replace(["\\\\", "\\'"], ["\\", "'"], $value);
        }
        return stripcslashes($value);
    }
}
