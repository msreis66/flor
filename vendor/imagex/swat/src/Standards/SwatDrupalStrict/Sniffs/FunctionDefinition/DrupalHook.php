<?php

namespace Imagex\Swat\Standards\SwatDrupalStrict\Sniffs\FunctionDefinition;

use Drupal\Sniffs\Semantics\FunctionDefinition;
use PHP_CodeSniffer\Files\File;

abstract class DrupalHook extends FunctionDefinition {

  /**
   * Get the functions docblock comment tokens.
   */
  protected function getDocTokens(File $phpcsFile, int $stackPtr, int $functionPtr): array|false {
    $tokens = $phpcsFile->getTokens();
    $docCommentEnd = $phpcsFile->findPrevious(T_WHITESPACE, $functionPtr - 1, NULL, TRUE);

    if ($docCommentEnd === FALSE || $tokens[$docCommentEnd]['code'] !== T_DOC_COMMENT_CLOSE_TAG) {
      return FALSE;
    }
    $docCommentStart = $phpcsFile->findPrevious(T_DOC_COMMENT_OPEN_TAG, $docCommentEnd);

    return array_slice($tokens, $docCommentStart, $docCommentEnd - $docCommentStart + 1, TRUE);
  }

  /**
   * Find the functions docblock comment position.
   *
   * @param array $docTokens
   *   All of the docblock comment tokens.
   * @param string $comment
   *   The comment to find within the docblock tokens.
   */
  protected function findDocComment(array $docTokens, string &$comment): int|false {
    foreach ($docTokens as $docPtr => $docToken) {
      if ($docToken['code'] === T_DOC_COMMENT_STRING) {
        // Check for an existing comment.
        if (str_starts_with($docToken['content'], $comment)) {
          return FALSE;
        }
        // Track position of the first comment.
        if (empty($firstCommentPtr)) {
          $firstCommentPtr = $docPtr;
        }
        // Track position of an existing implements comment.
        if (empty($commentPtr) && str_starts_with($docToken['content'], "Implements")) {
          $implementsPtr = $docPtr;
        }
      }
    }
    if (empty($firstCommentPtr) && empty($implementsPtr)) {
      return FALSE;
    }

    // Add comment to the existing docblock comment.
    if (empty($implementsPtr)) {
      $implementsPtr = $firstCommentPtr;
      $comment = "$comment\n\n * {$docTokens[$firstCommentPtr]['content']}";
    }

    return $implementsPtr;
  }

}
