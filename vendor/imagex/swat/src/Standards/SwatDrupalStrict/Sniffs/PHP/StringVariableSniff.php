<?php

declare(strict_types=1);

namespace Imagex\Swat\Standards\SwatDrupalStrict\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class StringVariableSniff implements Sniff {

  public function register(): array {
    return [T_DOUBLE_QUOTED_STRING];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): void {
    $tokens = $phpcsFile->getTokens();
    $string = $tokens[$stackPtr]['content'];

    $this->processSimple($phpcsFile, $stackPtr, $string);
    $this->processComplex($phpcsFile, $stackPtr, $string);
  }

  protected function processSimple(File $phpcsFile, int $stackPtr, string $string): void {
    $pattern = '/\{\$(([a-z][\w_]*)\??(->|\[\d*])?(\w*))}(?![_\[}])/i';
    if (!preg_match($pattern, $string, $matches)) {
      return;
    }
    if (isset($matches[5])) {
      return;
    }
    $fix = $phpcsFile->addFixableError(
      'Simple variables inside double quotes strings do not need to be wrapped with curly braces.',
      $stackPtr,
      'UselessCurlyBraces'
    );
    if (!$fix) {
      return;
    }

    // Remove useless curly braces.
    $phpcsFile->fixer->replaceToken($stackPtr, preg_replace($pattern, '$\1', $string));
  }

  protected function processComplex(File $phpcsFile, int $stackPtr, string $string): void {
    $pattern = '/(?<!\{)\$[a-z]\w*->[a-z]\w+(((\([^)]*\)|\??->)([a-z]\w*)?)+)/i';
    if (!preg_match($pattern, $string, $matches)) {
      return;
    }
    $fix = $phpcsFile->addFixableError(
      'Complex variables inside double quoted strings need to be wrapped with curly braces.',
      $stackPtr,
      'MissingCurlyBraces'
    );
    if (!$fix) {
      return;
    }

    // Add missing curly braces.
    $var = '{' . trim($matches[0], '{}') . '}';
    $phpcsFile->fixer->replaceToken($stackPtr, preg_replace($pattern, $var, $string));
  }

}
