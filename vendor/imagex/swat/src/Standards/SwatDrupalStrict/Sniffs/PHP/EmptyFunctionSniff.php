<?php

declare(strict_types=1);

namespace Imagex\Swat\Standards\SwatDrupalStrict\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;

final class EmptyFunctionSniff implements Sniff {

  public function register(): array {
    return [T_FUNCTION];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr): void {
    if (!$phpcsFile->findPrevious([T_CLASS, T_ANON_CLASS, T_TRAIT, T_ENUM], $stackPtr)) {
      return;
    }

    // If no scope means abstract/interface method - skip.
    if (!isset($tokens[$stackPtr]['scope_opener']) || !isset($tokens[$stackPtr]['scope_closer'])) {
      return;
    }

    $tokens = $phpcsFile->getTokens();
    $functionStart = $tokens[$stackPtr]['scope_opener'];
    $functionEnd = $tokens[$stackPtr]['scope_closer'];
    if ($functionStart === $functionEnd - 1) {
      return;
    }

    // Function is not empty.
    $lines = array_filter(
      array_slice($tokens, $functionStart + 1, $functionEnd - $functionStart - 1),
      fn ($token) => $token['code'] !== T_WHITESPACE
    );
    if (count($lines) !== 0) {
      return;
    }

    $fix = $phpcsFile->addFixableError('Empty functions should be on a single line.', $stackPtr, 'Whitespace');
    if (!$fix) {
      return;
    }

    // Remove empty lines.
    $phpcsFile->fixer->beginChangeset();
    for ($i = $functionStart + 1; $i < $functionEnd; $i++) {
      $phpcsFile->fixer->replaceToken($i, '');
    }
    $phpcsFile->fixer->endChangeset();
  }

}
