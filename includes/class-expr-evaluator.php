<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }
/**
 * Pelican_Expr_Evaluator — tiny safe expression evaluator for calculated columns.
 *
 * Supports + - * / parentheses, integer + float literals. NOT eval().
 * Placeholders ({order_total}, {tax}, …) must be substituted by the caller
 * before passing the expression here.
 *
 * @package Pelican
 */
class Pelican_Expr_Evaluator {

    private $tokens = array();
    private $pos    = 0;

    public static function eval_expr( $expr ) {
        $self = new self();
        return $self->run( (string) $expr );
    }

    private function run( $expr ) {
        $this->tokens = $this->tokenize( $expr );
        $this->pos    = 0;
        if ( empty( $this->tokens ) ) return 0;
        $val = $this->parse_expr();
        if ( $this->pos < count( $this->tokens ) ) {
            throw new \RuntimeException( 'Unexpected token: ' . $this->tokens[ $this->pos ][1] );
        }
        return $val;
    }

    private function tokenize( $s ) {
        $s = trim( $s );
        $tokens = array();
        $len = strlen( $s );
        $i = 0;
        while ( $i < $len ) {
            $c = $s[ $i ];
            if ( ctype_space( $c ) ) { $i++; continue; }
            if ( ctype_digit( $c ) || $c === '.' ) {
                $num = '';
                while ( $i < $len && ( ctype_digit( $s[ $i ] ) || $s[ $i ] === '.' ) ) { $num .= $s[ $i++ ]; }
                $tokens[] = array( 'num', (float) $num );
                continue;
            }
            if ( in_array( $c, array( '+', '-', '*', '/', '(', ')' ), true ) ) {
                $tokens[] = array( 'op', $c );
                $i++;
                continue;
            }
            throw new \RuntimeException( 'Unsupported character in expression: ' . $c );
        }
        return $tokens;
    }

    private function peek() { return isset( $this->tokens[ $this->pos ] ) ? $this->tokens[ $this->pos ] : null; }
    private function consume() { return $this->tokens[ $this->pos++ ]; }

    /** expr = term ( ("+" | "-") term )* */
    private function parse_expr() {
        $val = $this->parse_term();
        while ( ( $tok = $this->peek() ) && $tok[0] === 'op' && ( $tok[1] === '+' || $tok[1] === '-' ) ) {
            $op = $this->consume()[1];
            $rhs = $this->parse_term();
            $val = $op === '+' ? $val + $rhs : $val - $rhs;
        }
        return $val;
    }

    /** term = factor ( ("*" | "/") factor )* */
    private function parse_term() {
        $val = $this->parse_factor();
        while ( ( $tok = $this->peek() ) && $tok[0] === 'op' && ( $tok[1] === '*' || $tok[1] === '/' ) ) {
            $op = $this->consume()[1];
            $rhs = $this->parse_factor();
            if ( $op === '/' ) {
                if ( $rhs == 0 ) return 0; /* graceful — never throw on /0 in a column expr */
                $val = $val / $rhs;
            } else {
                $val = $val * $rhs;
            }
        }
        return $val;
    }

    /** factor = "-" factor | "(" expr ")" | num */
    private function parse_factor() {
        $tok = $this->peek();
        if ( ! $tok ) throw new \RuntimeException( 'Unexpected end of expression' );
        if ( $tok[0] === 'op' && $tok[1] === '-' ) { $this->consume(); return - $this->parse_factor(); }
        if ( $tok[0] === 'op' && $tok[1] === '+' ) { $this->consume(); return   $this->parse_factor(); }
        if ( $tok[0] === 'op' && $tok[1] === '(' ) {
            $this->consume();
            $val = $this->parse_expr();
            $next = $this->peek();
            if ( ! $next || $next[0] !== 'op' || $next[1] !== ')' ) throw new \RuntimeException( 'Missing closing parenthesis' );
            $this->consume();
            return $val;
        }
        if ( $tok[0] === 'num' ) { return (float) $this->consume()[1]; }
        throw new \RuntimeException( 'Unexpected token: ' . $tok[1] );
    }
}
