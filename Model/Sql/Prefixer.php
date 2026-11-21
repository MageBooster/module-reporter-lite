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

use Magento\Framework\App\ResourceConnection;

/**
 * Finds the real tables a query reads and adds the Magento table prefix (if the store has one) to them,
 * so the same query works with and without a prefix.
 *
 * Tables are found after FROM (also comma lists: FROM a, b x, c AS y), after every JOIN, inside subqueries
 * and inside WITH (CTE) bodies. Names may be bare or in backticks. CTE names, subquery aliases, names with a
 * schema (db.table), table functions, DUAL, names that already carry the prefix and names that are not
 * Magento tables stay as they are.
 */
class Prefixer
{
    /**
     * Words that end a table reference instead of being its alias
     */
    private const STOP = [
        'AS', 'ON', 'USING', 'WHERE', 'GROUP', 'ORDER', 'HAVING', 'LIMIT', 'UNION', 'EXCEPT', 'INTERSECT',
        'WINDOW', 'FOR', 'LOCK', 'INTO', 'JOIN', 'INNER', 'LEFT', 'RIGHT', 'CROSS', 'NATURAL', 'FULL', 'OUTER',
        'STRAIGHT_JOIN', 'USE', 'FORCE', 'IGNORE', 'PARTITION', 'SELECT', 'WITH', 'SET', 'OFFSET', 'TABLESAMPLE',
    ];

    /**
     * Functions whose arguments may contain the word FROM
     */
    private const FROM_FUNCTIONS = ['EXTRACT', 'TRIM', 'SUBSTRING', 'SUBSTR', 'OVERLAY', 'MID'];

    /**
     * @var string[]|null
     */
    private $tables = null;

    /**
     * @param ResourceConnection $resource
     * @param Validator $validator
     */
    public function __construct(private ResourceConnection $resource, private Validator $validator)
    {
    }

    /**
     * Table prefix from env.php
     *
     * @return string
     */
    public function prefix(): string
    {
        return (string)$this->resource->getTablePrefix();
    }

    /**
     * Query with prefixed table names
     *
     * @param string $sql query without comments
     * @return string
     */
    public function apply(string $sql): string
    {
        $prefix = $this->prefix();
        if ($prefix === '') {
            return $sql;
        }
        return $this->applyPrefix($sql, $prefix, fn(string $name): bool => $this->isTable($name));
    }

    /**
     * Query with $prefix added to every table reference for which $exists(prefixed name) is true
     *
     * @param string $sql query without comments
     * @param string $prefix
     * @param callable $exists receives the prefixed name
     * @return string
     */
    public function applyPrefix(string $sql, string $prefix, callable $exists): string
    {
        $edits = [];
        foreach ($this->references($sql) as $ref) {
            $name = $ref['name'];
            if (str_starts_with(strtolower($name), strtolower($prefix)) || !$exists($prefix . $name)) {
                continue;
            }
            $edits[] = $ref['offset'];
        }
        rsort($edits);
        foreach (array_unique($edits) as $at) {
            $sql = substr($sql, 0, $at) . $prefix . substr($sql, $at);
        }
        return $sql;
    }

    /**
     * Names of the tables the query reads, in order of appearance, without duplicates
     *
     * @param string $sql query without comments
     * @return string[]
     */
    public function tableNames(string $sql): array
    {
        $names = [];
        foreach ($this->references($sql) as $ref) {
            $names[strtolower($ref['name'])] = $ref['name'];
        }
        return array_values($names);
    }

    /**
     * Table references: name and the offset where the name starts (inside the backticks when quoted)
     *
     * @param string $sql query without comments
     * @return array<int,array{name:string,offset:int}>
     */
    public function references(string $sql): array
    {
        try {
            $masked = $this->validator->mask($sql, true);
        } catch (\Throwable $e) {
            return [];
        }
        $ctes = $this->cteNames($sql, $masked);
        $refs = [];
        if (!preg_match_all('/\b(FROM|JOIN)\b/i', $masked, $m, PREG_OFFSET_CAPTURE)) {
            return [];
        }
        foreach ($m[1] as [$word, $offset]) {
            if (strtoupper($word) === 'FROM' && $this->insideFromFunction($masked, $offset)) {
                continue;
            }
            $pos = $offset + strlen($word);
            $list = strtoupper($word) === 'FROM';
            do {
                $pos = $this->skipSpace($masked, $pos);
                $ref = $this->tableAt($sql, $masked, $pos);
                if ($ref === null) {
                    break;
                }
                $pos = $ref['end'];
                if ($ref['name'] !== null && !isset($ctes[strtolower($ref['name'])])) {
                    $refs[$ref['offset']] = ['name' => $ref['name'], 'offset' => $ref['offset']];
                }
                $pos = $this->skipAlias($masked, $pos);
                $pos = $this->skipSpace($masked, $pos);
                $more = $list && ($masked[$pos] ?? '') === ',';
                $pos += $more ? 1 : 0;
            } while ($more);
        }
        ksort($refs);
        return array_values($refs);
    }

    /**
     * Names defined by WITH ... AS ( ... ) (lower case keys)
     *
     * @param string $sql
     * @param string $masked
     * @return array<string,bool>
     */
    private function cteNames(string $sql, string $masked): array
    {
        $names = [];
        if (!preg_match_all('/\bWITH\b(\s+RECURSIVE\b)?/i', $masked, $m, PREG_OFFSET_CAPTURE)) {
            return $names;
        }
        foreach ($m[0] as [$text, $offset]) {
            $pos = $offset + strlen($text);
            do {
                $pos = $this->skipSpace($masked, $pos);
                $ident = $this->identAt($sql, $masked, $pos);
                if ($ident === null) {
                    break;
                }
                $pos = $this->skipSpace($masked, $ident['end']);
                if (($masked[$pos] ?? '') === '(') {
                    $pos = $this->skipSpace($masked, $this->closeParen($masked, $pos));
                }
                if (!preg_match('/\GAS\b/i', $masked, $as, 0, $pos)) {
                    break;
                }
                $pos = $this->skipSpace($masked, $pos + 2);
                if (($masked[$pos] ?? '') !== '(') {
                    break;
                }
                $names[strtolower($ident['name'])] = true;
                $pos = $this->skipSpace($masked, $this->closeParen($masked, $pos));
                $more = ($masked[$pos] ?? '') === ',';
                $pos += $more ? 1 : 0;
            } while ($more);
        }
        return $names;
    }

    /**
     * One table reference at $pos. name is null for a subquery, a schema-qualified name or a table function.
     *
     * @param string $sql
     * @param string $masked
     * @param int $pos
     * @return array{name:?string,offset:int,end:int}|null
     */
    private function tableAt(string $sql, string $masked, int $pos): ?array
    {
        if (($masked[$pos] ?? '') === '(') {
            return ['name' => null, 'offset' => $pos, 'end' => $this->closeParen($masked, $pos)];
        }
        $ident = $this->identAt($sql, $masked, $pos);
        if ($ident === null) {
            return null;
        }
        $end = $ident['end'];
        $next = $this->skipSpace($masked, $end);
        $ch = $masked[$next] ?? '';
        if ($ch === '.') {
            $second = $this->identAt($sql, $masked, $this->skipSpace($masked, $next + 1));
            return ['name' => null, 'offset' => $pos, 'end' => $second === null ? $next + 1 : $second['end']];
        }
        if ($ch === '(') {
            return ['name' => null, 'offset' => $pos, 'end' => $this->closeParen($masked, $next)];
        }
        if (!$ident['quoted'] && in_array(strtoupper($ident['name']), array_merge(self::STOP, ['DUAL']), true)) {
            return strtoupper($ident['name']) === 'DUAL' ? ['name' => null, 'offset' => $pos, 'end' => $end] : null;
        }
        return ['name' => $ident['name'], 'offset' => $ident['offset'], 'end' => $end];
    }

    /**
     * Skips an optional alias ([AS] name, also `name`) and index hints after a table reference
     *
     * @param string $masked
     * @param int $pos
     * @return int
     */
    private function skipAlias(string $masked, int $pos): int
    {
        $pos = $this->skipSpace($masked, $pos);
        if (preg_match('/\GAS\b\s*/i', $masked, $as, 0, $pos)) {
            $pos += strlen($as[0]);
            $ident = $this->identAt($masked, $masked, $pos);
            $pos = $ident === null ? $pos : $ident['end'];
        } else {
            $ident = $this->identAt($masked, $masked, $pos);
            if ($ident !== null && ($ident['quoted'] || !in_array(strtoupper($ident['name']), self::STOP, true))) {
                $pos = $ident['end'];
            }
        }
        $pos = $this->skipSpace($masked, $pos);
        if (($masked[$pos] ?? '') === '(') {
            $pos = $this->closeParen($masked, $pos);
        }
        while (preg_match('/\G\s*(USE|FORCE|IGNORE)\s+(INDEX|KEY)\b[^(]*\(/i', $masked, $hint, 0, $pos)) {
            $pos = $this->closeParen($masked, $pos + strlen($hint[0]) - 1);
        }
        return $pos;
    }

    /**
     * Identifier at $pos (bare or in backticks)
     *
     * @param string $sql
     * @param string $masked
     * @param int $pos
     * @return array{name:string,offset:int,end:int,quoted:bool}|null
     */
    private function identAt(string $sql, string $masked, int $pos): ?array
    {
        if (($masked[$pos] ?? '') === '`') {
            $end = strpos($masked, '`', $pos + 1);
            if ($end === false) {
                return null;
            }
            $name = str_replace('``', '`', substr($sql, $pos + 1, $end - $pos - 1));
            return ['name' => $name, 'offset' => $pos + 1, 'end' => $end + 1, 'quoted' => true];
        }
        if (!preg_match('/\G[A-Za-z_$][A-Za-z0-9_$]*/', $masked, $nm, 0, $pos)) {
            return null;
        }
        return ['name' => $nm[0], 'offset' => $pos, 'end' => $pos + strlen($nm[0]), 'quoted' => false];
    }

    /**
     * Position after the parenthesis that closes the one at $pos
     *
     * @param string $masked
     * @param int $pos
     * @return int
     */
    private function closeParen(string $masked, int $pos): int
    {
        $depth = 0;
        $n = strlen($masked);
        for ($i = $pos; $i < $n; $i++) {
            if ($masked[$i] === '(') {
                $depth++;
            } elseif ($masked[$i] === ')' && --$depth === 0) {
                return $i + 1;
            }
        }
        return $n;
    }

    /**
     * Is this FROM an argument of EXTRACT(... FROM ...), TRIM(... FROM ...) and the like?
     *
     * @param string $masked
     * @param int $offset
     * @return bool
     */
    private function insideFromFunction(string $masked, int $offset): bool
    {
        $depth = 0;
        for ($i = $offset - 1; $i >= 0; $i--) {
            if ($masked[$i] === ')') {
                $depth++;
            } elseif ($masked[$i] === '(') {
                if ($depth-- > 0) {
                    continue;
                }
                $before = rtrim(substr($masked, 0, $i));
                return (bool)preg_match('/\b(' . implode('|', self::FROM_FUNCTIONS) . ')$/i', $before);
            }
        }
        return false;
    }

    /**
     * Position of the next non-space character
     *
     * @param string $masked
     * @param int $pos
     * @return int
     */
    private function skipSpace(string $masked, int $pos): int
    {
        $n = strlen($masked);
        while ($pos < $n && ctype_space($masked[$pos])) {
            $pos++;
        }
        return $pos;
    }

    /**
     * Does this (prefixed) table exist?
     *
     * @param string $name
     * @return bool
     */
    private function isTable(string $name): bool
    {
        if ($this->tables === null) {
            $this->tables = array_flip(array_map('strtolower', $this->resource->getConnection()->getTables()));
        }
        return isset($this->tables[strtolower($name)]);
    }
}
