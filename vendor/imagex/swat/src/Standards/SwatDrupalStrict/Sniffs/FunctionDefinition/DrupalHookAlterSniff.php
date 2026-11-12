<?php

declare(strict_types=1);

namespace Imagex\Swat\Standards\SwatDrupalStrict\Sniffs\FunctionDefinition;

use DrupalPractice\Project;
use PHP_CodeSniffer\Files\File;

final class DrupalHookAlterSniff extends DrupalHook {

  /**
   * {@inheritdoc}
   */
  public function processFunction(File $phpcsFile, $stackPtr, $functionPtr) {
    $functionName = $phpcsFile->getDeclarationName($functionPtr);
    if (!preg_match('/^([a-z]\w+)_alter$/i', $functionName)) {
      return;
    }
    if (!$docTokens = $this->getDocTokens($phpcsFile, $stackPtr, $functionPtr)) {
      return;
    }
    if (!$name = Project::getName($phpcsFile)) {
      return;
    }
    if (!preg_match("/^{$name}_([\w_]+)_alter$/i", $functionName, $matches)) {
      return;
    }
    $alter = $matches[1];

    // Use hook_form_FORM_ID_alter() for form alters.
    if ($functionName === "{$name}_form_alter") {
      $comment = 'Implements hook_form_alter().';
      $message = 'Improperly formatted hook_form_alter() comment.';
    }
    elseif (preg_match("/^{$name}_form_([\w_]+)_alter$/i", $functionName, $matches)) {
      $comment = "Implements hook_form_FORM_ID_alter() for $matches[1].";
      $message = 'Improperly formatted hook_form_FORM_ID_alter() comment.';
    }
    // Use hook_HOOK_alter() for all others.
    else {
      $comment = "Implements hook_{$alter}_alter().";
      $message = 'Improperly formatted hook_HOOK_alter() comment.';
    }

    $docCommentPtr = $this->findDocComment($docTokens, $comment);
    if ($docCommentPtr === FALSE) {
      return;
    }

    $fix = $phpcsFile->addFixableError($message, key($docTokens), 'Comment');
    if (!$fix) {
      return;
    }
    $phpcsFile->fixer->replaceToken($docCommentPtr, $comment);
  }

}
