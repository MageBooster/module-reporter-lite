<?php
/*
 * Copyright (c) 2026 WebWorks 101 OÜ (MageBooster). All rights reserved.
 * Commercial licence, see LICENSE.txt. https://magebooster.com
 * Licensed per serial number for one production installation (one store domain); staging and
 * development copies of that store are allowed. Serial numbers and downloads are tied to the customer.
 * You may modify this code for your own licensed store; keep this notice.
 * Not allowed: copying to other stores, redistributing, reselling, sublicensing, publishing it publicly,
 * removing licence checks or notices, sharing serial numbers. Unauthorised use: the serial number is
 * revoked without refund, updates and support stop, damages and legal action may follow.
 * Estonian law applies; court: Harju County Court (Harju Maakohus).
 */
declare(strict_types=1);

namespace MageBooster\ReporterLite\Model\Sql;

use Magento\Framework\Exception\LocalizedException;

/**
 * Accepts one SELECT (or WITH ... SELECT) statement and nothing else.
 *
 * This is the first of three guards. The others are the read-only database user and the read-only session
 * with a statement time limit (see Connection). Strings and comments are masked before the checks, so a
 * keyword inside a string does not trigger them and a statement hidden in a comment cannot run.
 */
class Validator
{
    public const MAX_LENGTH = 30000;

    private const SYSTEM_SCHEMAS = ['INFORMATION_SCHEMA', 'PERFORMANCE_SCHEMA', 'MYSQL', 'SYS'];

    /**
     * Words that never belong in a report query. Common column names (user, event, source, password ...)
     * are not on the list; the read-only user stops writes anyway.
     */
    private const DENY = [
        'INSERT', 'UPDATE', 'DELETE', 'REPLACE', 'MERGE', 'CREATE', 'ALTER', 'DROP', 'TRUNCATE', 'RENAME',
        'GRANT', 'REVOKE', 'LOCK', 'UNLOCK', 'CALL', 'DO', 'HANDLER', 'LOAD', 'SET', 'EXECUTE', 'EXEC',
        'PREPARE', 'DEALLOCATE', 'KILL', 'SHUTDOWN', 'FLUSH', 'RESET', 'PURGE', 'INSTALL', 'UNINSTALL',
        'OUTFILE', 'DUMPFILE', 'LOAD_FILE', 'SLEEP', 'BENCHMARK', 'GET_LOCK', 'RELEASE_LOCK',
        'RELEASE_ALL_LOCKS', 'IS_FREE_LOCK', 'IS_USED_LOCK', 'INTO', 'USE', 'BEGIN', 'COMMIT', 'ROLLBACK',
        'SAVEPOINT', 'XA', 'ANALYZE', 'OPTIMIZE', 'REPAIR', 'BINLOG', 'SHOW', 'DESCRIBE', 'EXPLAIN',
        'INFORMATION_SCHEMA', 'PERFORMANCE_SCHEMA', 'MYSQL', 'SYS', 'NEXTVAL', 'SETVAL', 'LASTVAL',
        'PROCEDURE', 'TRIGGER', 'SYS_EXEC', 'SYS_EVAL', 'MASTER_POS_WAIT', 'SOURCE_POS_WAIT',
        'WAIT_FOR_EXECUTED_GTID_SET',
    ];

    /**
     * Checks the query and returns it without a trailing semicolon and comments, with the parameter names
     *
     * @param string $sql
     * @return array{sql:string,params:string[]}
     * @throws LocalizedException
     */
    public function check(string $sql): array
    {
        $sql = trim($sql);
        if ($sql === '') {
            throw new LocalizedException(__('Write a SELECT query first.'));
        }
        if (strlen($sql) > self::MAX_LENGTH) {
            throw new LocalizedException(__('The query is too long.'));
        }
        if (preg_match('#/\*[!M+]#', $sql)) {
            throw new LocalizedException(__('Version comments (/*! ... */) are not allowed.'));
        }
        $bare = rtrim(trim($this->mask($sql, false)), "; \t\r\n");
        if (str_contains($bare, ';')) {
            throw new LocalizedException(__('Only one statement can run at a time.'));
        }
        if (!preg_match('/^\(*\s*(SELECT|WITH)\b/i', ltrim($bare))) {
            throw new LocalizedException(__('Only SELECT queries can run here.'));
        }
        if (str_contains($bare, ':=') || str_contains($bare, '@')) {
            throw new LocalizedException(__('Variables (@...) are not allowed.'));
        }
        if (str_contains($bare, '?')) {
            throw new LocalizedException(__('Use named parameters like :from instead of "?".'));
        }
        if (preg_match_all('/\b[A-Za-z_][A-Za-z0-9_$]*\b/', $bare, $m)) {
            foreach (array_unique(array_map('strtoupper', $m[0])) as $word) {
                if (in_array($word, self::DENY, true)) {
                    throw new LocalizedException(__('"%1" is not allowed in a report query.', $word));
                }
            }
        }
        if (preg_match('/\bFOR\s+(UPDATE|SHARE)\b|\bLOCK\s+IN\b/i', $bare)) {
            throw new LocalizedException(__('Locking reads are not allowed.'));
        }
        $params = $this->params($bare);
        // the statement that runs: comments removed (PDO would read "?" or ":x" inside them), strings kept
        $real = rtrim(trim($this->stripComments($sql)), "; \t\r\n");
        if (str_contains($this->mask($real, false), ';')) {
            throw new LocalizedException(__('Only one statement can run at a time.'));
        }
        return ['sql' => $real, 'params' => $params];
    }

    /**
     * Parameter names (:name) used in the query, in order of appearance, without strings and comments
     *
     * @param string $sql
     * @return string[]
     */
    public function params(string $sql): array
    {
        try {
            $bare = $this->mask($sql, false);
        } catch (LocalizedException $e) {
            return [];
        }
        preg_match_all('/(?<![:\w]):([a-z_][a-z0-9_]{0,40})\b/i', $bare, $pm);
        return array_values(array_unique(array_map('strtolower', $pm[1])));
    }

    /**
     * Same-length copy with string contents, quoted identifiers and comments blanked
     *
     * With $keepLength false, quoted identifiers become the word "ident" and strings 'str', except system
     * schema names, which stay so that `information_schema`.`x` is refused like the bare name.
     *
     * @param string $sql
     * @param bool $keepLength
     * @return string
     * @throws LocalizedException
     */
    public function mask(string $sql, bool $keepLength = true): string
    {
        $out = '';
        foreach ($this->tokens($sql) as [$kind, $text]) {
            if ($kind === 'code') {
                $out .= $text;
            } elseif ($keepLength) {
                $out .= $kind === 'ident' || $kind === 'string'
                    ? $text[0] . str_repeat(' ', strlen($text) - 2) . $text[0]
                    : str_repeat(' ', strlen($text));
            } elseif ($kind === 'ident') {
                $name = strtoupper(str_replace('``', '`', substr($text, 1, -1)));
                $out .= in_array($name, self::SYSTEM_SCHEMAS, true) ? ' ' . $name . ' ' : ' ident ';
            } elseif ($kind === 'string') {
                $out .= " 'str' ";
            } else {
                $out .= ' ';
            }
        }
        return $out;
    }

    /**
     * The query without comments; strings and identifiers unchanged
     *
     * @param string $sql
     * @return string
     * @throws LocalizedException
     */
    public function stripComments(string $sql): string
    {
        $out = '';
        foreach ($this->tokens($sql) as [$kind, $text]) {
            $out .= $kind === 'comment' ? ($text[0] === '/' ? ' ' : "\n") : $text;
        }
        return $out;
    }

    /**
     * Splits SQL into code, string, quoted identifier and comment pieces
     *
     * @param string $sql
     * @return array<int,array{0:string,1:string}>
     * @throws LocalizedException
     */
    private function tokens(string $sql): array
    {
        $tokens = [];
        $code = '';
        $n = strlen($sql);
        for ($i = 0; $i < $n; $i++) {
            $ch = $sql[$i];
            $next = $sql[$i + 1] ?? '';
            $third = $sql[$i + 2] ?? "\n";
            $comment = null;
            if (($ch === '-' && $next === '-' && in_array($third, [' ', "\t", "\n", "\r"], true)) || $ch === '#') {
                $end = strpos($sql, "\n", $i);
                $end = $end === false ? $n : $end;
                $comment = substr($sql, $i, $end - $i);
                $i = $end - 1;
            } elseif ($ch === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                if ($end === false) {
                    throw new LocalizedException(__('A comment is not closed.'));
                }
                $comment = substr($sql, $i, $end + 2 - $i);
                $i = $end + 1;
            }
            if ($comment !== null) {
                $tokens[] = ['code', $code];
                $tokens[] = ['comment', $comment];
                $code = '';
                continue;
            }
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $j = $i + 1;
                while ($j < $n) {
                    if ($sql[$j] === '\\' && $ch !== '`') {
                        $j += 2;
                        continue;
                    }
                    if ($sql[$j] === $ch) {
                        if (($sql[$j + 1] ?? '') === $ch) {
                            $j += 2;
                            continue;
                        }
                        break;
                    }
                    $j++;
                }
                if ($j >= $n) {
                    throw new LocalizedException(__('A quote is not closed.'));
                }
                $tokens[] = ['code', $code];
                $tokens[] = [$ch === '`' ? 'ident' : 'string', substr($sql, $i, $j - $i + 1)];
                $code = '';
                $i = $j;
                continue;
            }
            $code .= $ch;
        }
        $tokens[] = ['code', $code];
        return $tokens;
    }
}
