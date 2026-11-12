<?php

declare(strict_types=1);

namespace Imagex\Swat\Standards\SwatDrupalStrict\Sniffs\FunctionDefinition;

use PHP_CodeSniffer\Files\File;

final class DrupalHookPreprocessSniff extends DrupalHook {

  /**
   * {@inheritdoc}
   */
  public function processFunction(File $phpcsFile, $stackPtr, $functionPtr) {
    $functionName = $phpcsFile->getDeclarationName($functionPtr);
    if (!preg_match('/^([a-z]\w+)_preprocess_(\w+)$/i', $functionName, $matches)) {
      return;
    }
    if (!$docTokens = $this->getDocTokens($phpcsFile, $stackPtr, $functionPtr)) {
      return;
    }

    $comment = "Implements hook_preprocess_HOOK().";
    if ($this->findDocComment($docTokens, $comment) === FALSE) {
      return;
    }

    $hook = $matches[2];
    $comment = "Implements hook_preprocess_HOOK() for $hook templates.";

    $docCommentPtr = $this->findDocComment($docTokens, $comment);
    if ($docCommentPtr === FALSE) {
      return;
    }

    $fix = $phpcsFile->addFixableError('Improperly formatted hook_preprocess_HOOK() comment.', key($docTokens), 'Comment');
    if (!$fix) {
      return;
    }
    $phpcsFile->fixer->replaceToken($docCommentPtr, $comment);
  }

}
