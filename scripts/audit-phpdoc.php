#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Audits project-owned PHP declarations for the documentation contract.
 *
 * The audit covers named classes, interfaces, traits, enums, functions, and
 * methods. Anonymous classes and closures are intentionally excluded because
 * PHPDoc cannot describe them as stable public declarations.
 *
 * @since 2.0.0
 */
final class PhpDocAudit
{
    /**
     * Token types that may appear between a PHPDoc block and its declaration.
     *
     * @var list<int>
     */
    private const DECLARATION_PREFIX_TOKENS = [
        T_ABSTRACT,
        T_FINAL,
        T_PRIVATE,
        T_PROTECTED,
        T_PUBLIC,
        T_STATIC,
        T_READONLY,
        T_WHITESPACE,
    ];

    /**
     * Audit every PHP source beneath the supplied paths.
     *
     * @param  list<string>  $paths  Project-relative files or directories.
     * @return list<array{file: string, line: int, declaration: string, issue: string}> Documentation violations.
     *
     * @since 2.0.0
     */
    public function audit(array $paths): array
    {
        $issues = [];

        foreach ($this->phpFiles($paths) as $file) {
            array_push($issues, ...$this->auditFile($file));
        }

        return $issues;
    }

    /**
     * Resolve unique PHP files from project-relative files and directories.
     *
     * @param  list<string>  $paths  Project-relative files or directories.
     * @return list<string> Sorted absolute PHP file paths.
     *
     * @since 2.0.0
     */
    private function phpFiles(array $paths): array
    {
        $files = [];

        foreach ($paths as $path) {
            $absolutePath = $this->absolutePath($path);

            if (is_file($absolutePath) && pathinfo($absolutePath, PATHINFO_EXTENSION) === 'php') {
                $files[] = $absolutePath;

                continue;
            }

            if (! is_dir($absolutePath)) {
                continue;
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($absolutePath, FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $files = array_values(array_unique($files));
        sort($files);

        return $files;
    }

    /**
     * Convert a project-relative path into an absolute path.
     *
     * @param  string  $path  Project-relative or absolute path.
     * @return string Absolute path.
     *
     * @since 2.0.0
     */
    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return $path;
        }

        return dirname(__DIR__).DIRECTORY_SEPARATOR.$path;
    }

    /**
     * Audit named declarations in one PHP source file.
     *
     * @param  string  $file  Absolute PHP file path.
     * @return list<array{file: string, line: int, declaration: string, issue: string}> File violations.
     *
     * @since 2.0.0
     */
    private function auditFile(string $file): array
    {
        $source = file_get_contents($file);

        if ($source === false) {
            return [[
                'file' => $this->relativePath($file),
                'line' => 1,
                'declaration' => 'file',
                'issue' => 'could not be read',
            ]];
        }

        $tokens = token_get_all($source);
        $issues = [];

        foreach ($tokens as $index => $token) {
            if (! is_array($token)) {
                continue;
            }

            if (in_array($token[0], [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM], true)) {
                $declaration = $this->classLikeDeclaration($tokens, $index);

                if ($declaration !== null) {
                    array_push(
                        $issues,
                        ...$this->documentationIssues(
                            $file,
                            $token[2],
                            $declaration,
                            $this->docCommentBefore($tokens, $index),
                            [],
                            false
                        )
                    );
                }
            }

            if ($token[0] !== T_FUNCTION) {
                continue;
            }

            $function = $this->functionDeclaration($tokens, $index);

            if ($function === null) {
                continue;
            }

            array_push(
                $issues,
                ...$this->documentationIssues(
                    $file,
                    $token[2],
                    $function['name'].'()',
                    $this->docCommentBefore($tokens, $index),
                    $function['parameters'],
                    ! in_array($function['name'], ['__construct', '__destruct'], true)
                )
            );
        }

        return $issues;
    }

    /**
     * Resolve a named class-like declaration and ignore anonymous classes.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens  PHP tokens.
     * @param  int  $index  Declaration token index.
     * @return string|null Declaration label or null for an anonymous class.
     *
     * @since 2.0.0
     */
    private function classLikeDeclaration(array $tokens, int $index): ?string
    {
        $previous = $this->previousSignificantToken($tokens, $index);

        if (
            is_array($previous)
            && in_array($previous[0], [T_NEW, T_DOUBLE_COLON], true)
        ) {
            return null;
        }

        $name = $this->nextNamedToken($tokens, $index);

        return $name === null ? null : $name;
    }

    /**
     * Parse a named function or method and its parameter names.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens  PHP tokens.
     * @param  int  $index  Function token index.
     * @return array{name: string, parameters: list<string>}|null Parsed declaration or null for a closure.
     *
     * @since 2.0.0
     */
    private function functionDeclaration(array $tokens, int $index): ?array
    {
        $name = $this->nextNamedToken($tokens, $index);

        if ($name === null) {
            return null;
        }

        $parameters = [];
        $depth = 0;
        $insideParameters = false;

        for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '(') {
                $depth++;
                $insideParameters = true;

                continue;
            }

            if (! $insideParameters) {
                continue;
            }

            if ($text === ')') {
                $depth--;

                if ($depth === 0) {
                    break;
                }

                continue;
            }

            if (is_array($token) && $token[0] === T_VARIABLE && $depth === 1) {
                $parameters[] = ltrim($token[1], '$');
            }
        }

        return ['name' => $name, 'parameters' => $parameters];
    }

    /**
     * Find the next declaration name after a class-like or function token.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens  PHP tokens.
     * @param  int  $index  Declaration token index.
     * @return string|null Declaration name, or null when none exists.
     *
     * @since 2.0.0
     */
    private function nextNamedToken(array $tokens, int $index): ?string
    {
        for ($cursor = $index + 1, $count = count($tokens); $cursor < $count; $cursor++) {
            $token = $tokens[$cursor];

            if (is_array($token) && $token[0] === T_STRING) {
                return $token[1];
            }

            if ($token === '(') {
                return null;
            }
        }

        return null;
    }

    /**
     * Find the declaration's PHPDoc across visibility and attribute prefixes.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens  PHP tokens.
     * @param  int  $index  Declaration token index.
     * @return string|null PHPDoc text when present.
     *
     * @since 2.0.0
     */
    private function docCommentBefore(array $tokens, int $index): ?string
    {
        $attributeDepth = 0;

        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if ($token === ']') {
                $attributeDepth++;

                continue;
            }

            if ($attributeDepth > 0) {
                if (is_array($token) && $token[0] === T_ATTRIBUTE) {
                    $attributeDepth--;
                } elseif ($token === '[') {
                    $attributeDepth--;
                }

                continue;
            }

            if (is_array($token) && $token[0] === T_DOC_COMMENT) {
                return $token[1];
            }

            if (is_array($token) && in_array($token[0], self::DECLARATION_PREFIX_TOKENS, true)) {
                continue;
            }

            if (is_array($token) && $token[0] === T_COMMENT) {
                continue;
            }

            if ($token === '&') {
                continue;
            }

            break;
        }

        return null;
    }

    /**
     * Return the previous token other than whitespace or comments.
     *
     * @param  array<int, array{0: int, 1: string, 2: int}|string>  $tokens  PHP tokens.
     * @param  int  $index  Starting token index.
     * @return array{0: int, 1: string, 2: int}|string|null Previous token.
     *
     * @since 2.0.0
     */
    private function previousSignificantToken(array $tokens, int $index): array|string|null
    {
        for ($cursor = $index - 1; $cursor >= 0; $cursor--) {
            $token = $tokens[$cursor];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }

    /**
     * Build documentation violations for a declaration.
     *
     * @param  string  $file  Absolute source path.
     * @param  int  $line  Declaration line.
     * @param  string  $declaration  Declaration label.
     * @param  string|null  $docComment  Existing PHPDoc text.
     * @param  list<string>  $parameters  Parameter names.
     * @param  bool  $requiresReturn  Whether an explicit return type tag is required.
     * @return list<array{file: string, line: int, declaration: string, issue: string}> Documentation violations.
     *
     * @since 2.0.0
     */
    private function documentationIssues(
        string $file,
        int $line,
        string $declaration,
        ?string $docComment,
        array $parameters,
        bool $requiresReturn
    ): array {
        $issues = [];

        if ($docComment === null) {
            return [[
                'file' => $this->relativePath($file),
                'line' => $line,
                'declaration' => $declaration,
                'issue' => 'missing PHPDoc',
            ]];
        }

        if (! $this->hasSummary($docComment)) {
            $issues[] = $this->issue($file, $line, $declaration, 'missing summary');
        }

        if (! preg_match('/@since\s+\d+\.\d+\.\d+/', $docComment)) {
            $issues[] = $this->issue($file, $line, $declaration, 'missing @since');
        }

        foreach ($parameters as $parameter) {
            if (! preg_match('/@param[^\r\n]*\s+(?:&\s*)?(?:\.\.\.\s*)?\$'.preg_quote($parameter, '/').'\b/', $docComment)) {
                $issues[] = $this->issue($file, $line, $declaration, 'missing typed @param $'.$parameter);
            }

            if (
                preg_match_all(
                    '/@param[^\r\n]*\s+(?:&\s*)?(?:\.\.\.\s*)?\$'.preg_quote($parameter, '/').'\b/',
                    $docComment
                ) > 1
            ) {
                $issues[] = $this->issue($file, $line, $declaration, 'duplicate @param $'.$parameter);
            }
        }

        if ($requiresReturn && ! preg_match('/@return\s+\S+/', $docComment)) {
            $issues[] = $this->issue($file, $line, $declaration, 'missing typed @return');
        }

        if ($requiresReturn && preg_match_all('/@return\s+\S+/', $docComment) > 1) {
            $issues[] = $this->issue($file, $line, $declaration, 'duplicate @return');
        }

        return $issues;
    }

    /**
     * Determine whether PHPDoc contains a prose summary before its tags.
     *
     * @param  string  $docComment  PHPDoc text.
     * @return bool True when a non-tag summary line exists.
     *
     * @since 2.0.0
     */
    private function hasSummary(string $docComment): bool
    {
        $body = preg_replace(['/^\/\*\*/', '/\*\/$/'], '', trim($docComment));

        if ($body === null) {
            return false;
        }

        foreach (preg_split('/\R/', $body) ?: [] as $line) {
            $line = trim(ltrim(trim($line), '*'));

            if ($line !== '' && ! str_starts_with($line, '@')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Create one normalized audit issue.
     *
     * @param  string  $file  Absolute source path.
     * @param  int  $line  Declaration line.
     * @param  string  $declaration  Declaration label.
     * @param  string  $issue  Human-readable issue.
     * @return array{file: string, line: int, declaration: string, issue: string} Normalized issue.
     *
     * @since 2.0.0
     */
    private function issue(string $file, int $line, string $declaration, string $issue): array
    {
        return [
            'file' => $this->relativePath($file),
            'line' => $line,
            'declaration' => $declaration,
            'issue' => $issue,
        ];
    }

    /**
     * Convert an absolute source path to a project-relative path.
     *
     * @param  string  $file  Absolute source path.
     * @return string Project-relative path.
     *
     * @since 2.0.0
     */
    private function relativePath(string $file): string
    {
        $root = dirname(__DIR__).DIRECTORY_SEPARATOR;

        return str_starts_with($file, $root) ? substr($file, strlen($root)) : $file;
    }
}

/**
 * Print PHPDoc violations and return a shell-friendly status code.
 *
 * @param  list<string>  $arguments  Command-line arguments.
 * @return int Zero when the audit passes; one when violations exist.
 *
 * @since 2.0.0
 */
function runPhpDocAudit(array $arguments): int
{
    $paths = array_slice($arguments, 1);

    if ($paths === []) {
        $paths = ['app'];
    }

    $issues = (new PhpDocAudit)->audit($paths);

    foreach ($issues as $issue) {
        fwrite(
            STDERR,
            sprintf(
                "%s:%d %s — %s\n",
                $issue['file'],
                $issue['line'],
                $issue['declaration'],
                $issue['issue']
            )
        );
    }

    if ($issues !== []) {
        fwrite(STDERR, sprintf("\n%d PHPDoc issue(s) found.\n", count($issues)));

        return 1;
    }

    fwrite(STDOUT, "PHPDoc audit passed.\n");

    return 0;
}

exit(runPhpDocAudit($argv));
