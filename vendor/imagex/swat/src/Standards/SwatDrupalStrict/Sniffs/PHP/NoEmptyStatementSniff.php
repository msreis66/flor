<?php

declare(strict_types=1);

namespace Imagex\Swat\Standards\SwatDrupalStrict\Sniffs\PHP;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Standards\Generic\Sniffs\CodeAnalysis\EmptyStatementSniff;

final class NoEmptyStatementSniff extends EmptyStatementSniff {

  public function register(): array {
    return [
      T_FUNCTION,
      T_TRY,
      T_DO,
      T_ELSE,
      T_ELSEIF,
      T_FOR,
      T_FOREACH,
      T_IF,
      T_SWITCH,
      T_WHILE,
      T_MATCH,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function process(File $phpcsFile, $stackPtr) {
    $tokens = $phpcsFile->getTokens();

    // Allow class functions to be empty.
    if ($tokens[$stackPtr]['code'] === T_FUNCTION
      && $phpcsFile->findPrevious([T_CLASS, T_ANON_CLASS, T_TRAIT, T_ENUM], $stackPtr)
    ) {
      return;
    }

    parent::process($phpcsFile, $stackPtr);
  }

}
