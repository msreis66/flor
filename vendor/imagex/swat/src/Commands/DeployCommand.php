<?php

namespace Imagex\Swat\Commands;

use Imagex\Swat\SwatTasks;
use Robo\Common\ProcessUtils;
use Robo\Contract\VerbosityThresholdInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Finder\Finder;

/**
 * Defines commands in the "deploy:*" namespace.
 */
class DeployCommand extends SwatTasks {

  /**
   * Whether to create a tag.
   *
   * @var bool
   */
  protected $createTag = FALSE;

  /**
   * Tag name.
   *
   * @var null
   */
  protected $tagName = NULL;

  /**
   * Branch name.
   *
   * @var string
   */
  protected $branchName;

  /**
   * Commit message.
   *
   * @var string
   */
  protected $commitMessage;

  /**
   * Exclude file tmp.
   *
   * @var string
   */
  protected $excludeFileTemp;

  /**
   * Deploy directory.
   *
   * @var string
   */
  protected $deployDir;

  /**
   * Deploy docroot directory.
   *
   * @var string
   */
  protected $deployDocroot;

  /**
   * Whether to tag source.
   *
   * @var bool
   */
  protected $tagSource;

  /**
   * Ignore platform requirements.
   *
   * @var bool
   */
  protected $ignorePlatformReqs = FALSE;

  /**
   * No Scripts.
   *
   * @var bool
   */
  protected $noScripts = FALSE;

  /**
   * This hook will fire for all commands in this command file.
   *
   * @hook init
   *
   * @throws \Exception
   */
  public function initialize() {
    $this->excludeFileTemp = $this->getConfigValue('deploy.exclude_file') . '.tmp';
    $this->deployDir = $this->getConfigValue('deploy.dir');
    $this->deployDocroot = $this->getConfigValue('deploy.docroot');
    if (!$this->deployDir || !$this->deployDocroot) {
      throw new \Exception('Configuration deploy.dir and deploy.docroot must be set to run this command');
    }
    $this->tagSource = $this->getConfigValue('deploy.tag_source', TRUE);
  }

  /**
   * Builds separate artifact and pushes to git.remotes defined swat.yml.
   *
   * @param array $options
   *   Options that can be passed via the CLI.
   *
   * @command artifact:deploy
   *
   * @aliases ad deploy:artifact
   *
   * @validateGitConfig
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  public function artifactDeploy(array $options = [
    'branch' => InputOption::VALUE_REQUIRED,
    'tag' => InputOption::VALUE_REQUIRED,
    'commit-msg' => InputOption::VALUE_REQUIRED,
    'ignore-dirty' => FALSE,
    'dry-run' => FALSE,
    'ignore-platform-reqs' => FALSE,
    'no-scripts' => FALSE,
  ]) {
    if ($options['dry-run']) {
      $this->logger->warning("This will be a dry run, the artifact will not be pushed.");
    }
    $this->checkDirty($options);

    if (isset($options['ignore-platform-reqs'])) {
      $this->ignorePlatformReqs = $options['ignore-platform-reqs'];
    }

    if (isset($options['no-scripts'])) {
      $this->noScripts = $options['no-scripts'];
    }

    $this->gitConfig();

    if (!$options['tag'] && !$options['branch']) {
      $this->createTag = $this->confirm("Would you like to create a tag?", $this->createTag);
    }
    $this->commitMessage = $this->getCommitMessage($options);

    if ($options['tag'] || $this->createTag) {
      // Warn if they're creating a tag and we won't tag the source for them.
      if (!$this->tagSource) {
        $this->say("Config option deploy.tag_source if FALSE. The source repo will not be tagged.");
      }
      $this->deployToTag($options);
    }
    else {
      $this->deployToBranch($options);
    }
  }

  /**
   * Checks to see if current git branch has uncommitted changes.
   *
   * @param array $options
   *   Set ignore-dirty to false to disable checks for dirty Git directory.
   *
   * @command artifact:deploy:check-dirty
   *
   * @aliases deploy:check-dirty
   *
   * @throws \Exception
   *   Thrown if there are uncommitted changes.
   */
  public function checkDirty(array $options = ['ignore-dirty' => FALSE]) {
    $result = $this->taskExec('git status --porcelain')
      ->printMetadata(FALSE)
      ->printOutput(TRUE)
      ->interactive(FALSE)
      ->run();
    if (!$options['ignore-dirty'] && !$result->wasSuccessful()) {
      throw new \Exception("Unable to determine if local git repository is dirty.");
    }

    $dirty = (bool) $result->getMessage();
    if ($dirty) {
      if ($options['ignore-dirty']) {
        $this->logger->warning("There are uncommitted changes on the source repository.");
      }
      else {
        if ($options['verbose']) {
          $this->taskExec('git diff --exit-code')
            ->printMetadata(FALSE)
            ->printOutput(TRUE)
            ->interactive(FALSE)
            ->run();
        }
        throw new \Exception("There are uncommitted changes on the source repository (listed above). Commit, stash, or remove these changes before deploying, or use the --ignore-dirty flag.");
      }
    }
  }

  /**
   * Gets the commit message to be used for committing deployment artifact.
   *
   * Defaults to the last commit message on the source branch.
   *
   * @param array $options
   *   CLI options for command.
   *
   * @return string
   *   The commit message.
   */
  protected function getCommitMessage(array $options) {
    if (!$options['commit-msg']) {
      chdir($this->getConfigValue('repo.root'));
      $log = explode(' ', shell_exec("git log --oneline -1"), 2);
      $git_last_commit_message = trim($log[1]);

      return $this->askDefault('Enter a valid commit message', $git_last_commit_message);
    }
    else {
      $this->say("Commit message is set to <comment>{$options['commit-msg']}</comment>.");
      return $options['commit-msg'];
    }
  }

  /**
   * Gets the branch name for the deployment artifact.
   *
   * Defaults to [current-branch]-build.
   *
   * @return string
   *   The branch name.
   */
  protected function getBranchName($options) {
    if ($options['branch']) {
      $this->say("Branch is set to <comment>{$options['branch']}</comment>.");
      return $options['branch'];
    }
    else {
      return $this->askDefault('Enter the branch name for the deployment artifact', $this->getDefaultBranchName());
    }
  }

  /**
   * Gets the name of the tag to cut.
   *
   * @param array $options
   *   Options.
   *
   * @return string
   *   Name.
   *
   * @throws \Exception
   */
  protected function getTagName(array $options) {
    if ($options['tag']) {
      $tag_name = $options['tag'];
    }
    else {
      $tag_name = $this->ask('Enter the tag name for the deployment artifact, e.g., 1.0.0-build');
    }

    if (empty($tag_name)) {
      // @todo Validate tag name is valid, e.g., no spaces or special characters.
      throw new \Exception("You must enter a valid tag name.");
    }
    else {
      $this->say("Tag is set to <comment>$tag_name</comment>.");
    }

    return $tag_name;
  }

  /**
   * Gets the default branch name for the deployment artifact.
   */
  protected function getDefaultBranchName() {
    chdir($this->getConfigValue('repo.root'));
    $git_current_branch = trim(shell_exec("git rev-parse --abbrev-ref HEAD"));
    $default_branch = $git_current_branch . '-build';

    return $default_branch;
  }

  /**
   * Creates artifact, cuts new tag, and pushes.
   *
   * @throws \Exception
   */
  protected function deployToTag($options) {
    $this->tagName = $this->getTagName($options);

    // If we are building a tag, then we assume that we will NOT be pushing the
    // build branch from which the tag is created. However, we must still have a
    // local branch from which to cut the tag, so we create a temporary one.
    $this->branchName = $this->getDefaultBranchName() . '-temp';
    $this->prepareDir();
    $this->addGitRemotes();
    $this->checkoutLocalDeployBranch();
    $this->build();
    $this->commit();
    $this->cutTag('build');

    // Check the deploy.tag_source config value and also tag the source repo if
    // it is set to TRUE (the default).
    if ($this->tagSource) {
      $this->cutTag('source');
    }

    $this->push($this->tagName, $options);
  }

  /**
   * Creates artifact on branch and pushes.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function deployToBranch($options) {
    $this->branchName = $this->getBranchName($options);
    $this->prepareDir();
    $this->addGitRemotes();
    $this->checkoutLocalDeployBranch();
    $this->mergeUpstreamChanges();
    $this->build();
    $this->commit();
    $this->push($this->branchName, $options);
  }

  /**
   * Deletes the existing deploy directory and initializes git repo.
   *
   * @throws \Exception
   * @throws \Robo\Exception\TaskException
   */
  protected function prepareDir() {
    $this->say("Preparing artifact directory...");
    $deploy_dir = $this->deployDir;
    $this->taskDeleteDir($deploy_dir)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    $result = $this->taskFilesystemStack()
      ->mkdir($this->deployDir)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception('Failed to create deploy directory');
    }
    $result = $this->taskExecStack()
      ->dir($deploy_dir)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->exec("git init")
      ->exec("git config --local core.excludesfile false")
      ->exec("git config --local core.fileMode true")
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception('Failed to initialize git repo');
    }
    $this->say("Global .gitignore file is being disabled for this repository to prevent unexpected behavior.");
  }

  /**
   * Adds remotes from git.remotes to /deploy repository.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function addGitRemotes() {
    $git_remotes = $this->getConfigValue('git.remotes');
    if (empty($git_remotes)) {
      throw new \Exception("git.remotes is empty. Please define at least one value for git.remotes in swat/swat.yml.");
    }
    foreach ($git_remotes as $remote_url) {
      $this->addGitRemote($remote_url);
    }
  }

  /**
   * Adds a single remote to the /deploy repository.
   *
   * @param string $remote_url
   *   Remote URL.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function addGitRemote($remote_url) {
    // Generate an md5 sum of the remote URL to use as remote name.
    $remote_name = md5($remote_url);
    $result = $this->taskExecStack()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->dir($this->deployDir)
      ->exec("git remote add $remote_name $remote_url")
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception('Failed to add remote');
    }
  }

  /**
   * Checks out a new, local branch for artifact.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function checkoutLocalDeployBranch() {
    $result = $this->taskExecStack()
      ->dir($this->deployDir)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->exec("git checkout -b {$this->branchName}")
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception('Failed to check out branch');
    }
  }

  /**
   * Merges upstream changes into deploy branch.
   *
   * @throws \Exception
   * @throws \Robo\Exception\TaskException
   */
  protected function mergeUpstreamChanges() {
    $git_remotes = $this->getConfigValue('git.remotes');
    $remote_url = reset($git_remotes);
    $remote_name = md5($remote_url);

    $this->say("Merging upstream changes into local artifact...");

    // Check if remote branch exists before fetching.
    $result = $this->taskExecStack()
      ->dir($this->deployDir)
      ->stopOnFail(FALSE)
      ->exec("git ls-remote --exit-code --heads $remote_url {$this->branchName}")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    switch ($result->getExitCode()) {
      case 0:
        // The remote branch exists, continue and merge it.
        break;

      case 2:
        // The remote branch doesn't exist, bail out.
        return;

      default:
        // Some other error code.
        throw new \Exception("Unexpected error while searching for remote branch: " . $result->getMessage());
    }

    // Now we know the remote branch exists, let's fetch and merge it.
    $result = $this->taskExecStack()
      ->dir($this->deployDir)
      ->exec("git fetch $remote_name {$this->branchName} --depth=1")
      ->exec("git merge $remote_name/{$this->branchName}")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception('Failed to merge branch');
    }
  }

  /**
   * Builds deployment artifact.
   *
   * @command artifact:build
   * @aliases ab deploy:build
   *
   * @param array $options
   *   CLI options for Composer behavior.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  public function build(array $options = [
    'ignore-platform-reqs' => FALSE,
    'no-scripts' => FALSE,
  ]) {

    if (isset($options['ignore-platform-reqs'])) {
      $this->ignorePlatformReqs = $options['ignore-platform-reqs'];
    }

    if (isset($options['no-scripts'])) {
      $this->noScripts = $options['no-scripts'];
    }

    $this->say("Generating build artifact...");
    $this->say("For more detailed output, use the -v flag.");

    $commands = ['frontend'];
    if (!empty($this->tagName)) {
      $commands['swat:deployment-identifier'] = ['--id' => $this->tagName];
    }
    else {
      $commands[] = 'swat:deployment-identifier';
    }
    $this->invokeCommands($commands);

    $this->buildCopy();
    $this->composerInstall();
    $this->sanitize();

    // Allow modifications after build.
    $this->invokeHooks(['post-artifact-build']);

    $this->say("<info>The deployment artifact was generated at {$this->deployDir}.</info>");
  }

  /**
   * Copies files from source repo into artifact.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function buildCopy() {
    $exclude_list_file = $this->getExcludeListFile();
    $source = $this->getConfigValue('repo.root');
    $dest = $this->deployDir;

    $this->say("Rsyncing files from source repo into the build artifact...");
    // @todo look into replacing with taskRsync.
    $result = $this->taskExecStack()->exec("rsync -a --no-g --delete --delete-excluded --exclude-from='$exclude_list_file' '$source/' '$dest/' --filter 'protect /.git/'")
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->dir($this->getConfigValue('repo.root'))
      ->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception('Failed to rsync artifact');
    }

    // Remove temporary file that may have been created by getExcludeListFile().
    $this->taskFilesystemStack()
      ->remove($this->excludeFileTemp)
      ->run();

    // Merge .gitignore files and copy to deploy dir.
    $gitignore_file = $this->getConfigValue('deploy.gitignore_file');
    $gitignore_additions_file = $this->getConfigValue('deploy.gitignore_additions_file');
    $merged_gitignore = $this->mungeGitignoreLists($gitignore_file, $gitignore_additions_file);
    $this->taskFilesystemStack()
      ->copy($merged_gitignore, $this->deployDir . '/.gitignore', TRUE)
      ->remove($merged_gitignore)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
  }

  /**
   * Installs composer dependencies for artifact.
   *
   * @return bool
   *   Bool.
   *
   * @throws \Robo\Exception\TaskException
   * @throws \Exception
   */
  protected function composerInstall() {
    if (!$this->getConfigValue('deploy.build-dependencies')) {
      $this->logger->warning("Dependencies will not be built because deploy.build-dependencies is not enabled");
      $this->logger->warning("You should define a custom deploy.exclude_file to ensure that dependencies are copied from the root repository.");

      return FALSE;
    }
    $this->say("Rebuilding composer dependencies for production...");
    $this->taskDeleteDir([$this->deployDir . '/vendor'])
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    $this->taskFilesystemStack()
      ->copy($this->getConfigValue('repo.root') . '/composer.json', $this->deployDir . '/composer.json', TRUE)
      ->copy($this->getConfigValue('repo.root') . '/composer.lock', $this->deployDir . '/composer.lock', TRUE)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();
    $command = 'composer install --no-dev --no-interaction --optimize-autoloader';
    if ($this->ignorePlatformReqs) {
      $command .= ' --ignore-platform-reqs';
    }
    if ($this->noScripts) {
      $command .= ' --no-scripts';
    }
    $execution_result = $this->taskExecStack()->exec($command)
      ->dir($this->deployDir)
      ->run();
    if (!$execution_result->wasSuccessful()) {
      throw new \Exception("Composer install failed, please check the output for details.");
    }
  }

  /**
   * Removes sensitive files from the deploy dir.
   */
  protected function sanitize() {
    $this->say("Sanitizing artifact...");

    $this->logger->info("Find Drupal core text files...");
    $sanitizeFinder = Finder::create()
      ->files()
      ->name('*.txt')
      ->notName('LICENSE.txt')
      ->in("{$this->deployDocroot}/core");

    $this->logger->info('Find VCS directories...');
    $vcsFinder = Finder::create()
      ->ignoreDotFiles(FALSE)
      ->ignoreVCS(FALSE)
      ->directories()
      ->in([$this->deployDocroot,
        "{$this->deployDir}/vendor",
      ])
      ->name('.git');
    $drush_dir = "{$this->deployDir}/drush";
    if (file_exists($drush_dir)) {
      $vcsFinder->in($drush_dir);
    }
    if ($vcsFinder->hasResults()) {
      $sanitizeFinder->append($vcsFinder);
    }

    $this->logger->info("Find .gitignore files...");
    $gitignoreFinder = Finder::create()
      ->ignoreDotFiles(FALSE)
      ->files()
      ->name('.gitignore')
      ->notPath([
        "sites/g/.gitignore",
        "sites/default/.gitignore",
      ])
      ->in("{$this->deployDocroot}");
    if ($gitignoreFinder->hasResults()) {
      $sanitizeFinder->append($gitignoreFinder);
    }

    $this->logger->info("Find Github directories...");
    $githubFinder = Finder::create()
      ->ignoreDotFiles(FALSE)
      ->directories()
      ->in([$this->deployDocroot, "{$this->deployDir}/vendor"])
      ->name('.github');
    if ($githubFinder->hasResults()) {
      $sanitizeFinder->append($githubFinder);
    }

    $this->logger->info('Find INSTALL database text files...');
    $dbInstallFinder = Finder::create()
      ->files()
      ->in([$this->deployDocroot])
      ->name('/INSTALL\.[a-z]+\.(md|txt)$/');
    if ($dbInstallFinder->hasResults()) {
      $sanitizeFinder->append($dbInstallFinder);
    }

    $this->logger->info('Find other common text files...');
    $filenames = [
      'AUTHORS',
      'CHANGELOG',
      'CONDUCT',
      'CONTRIBUTING',
      'INSTALL',
      'MAINTAINERS',
      'PATCHES',
      'TESTING',
      'UPDATE',
    ];
    $textFileFinder = Finder::create()
      ->files()
      ->in([$this->deployDocroot])
      ->name('/(' . implode('|', $filenames) . ')\.(md|txt)$/');
    if ($textFileFinder->hasResults()) {
      $sanitizeFinder->append($textFileFinder);
    }

    $this->logger->info("Remove sanitized files from build...");
    $taskFilesystemStack = $this->taskFilesystemStack()
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE);
    foreach ($sanitizeFinder->getIterator() as $fileInfo) {
      $taskFilesystemStack->remove($fileInfo->getRealPath());
    }
    $taskFilesystemStack->run();
  }

  /**
   * Gets the file that lists the excludes for the artifact.
   */
  protected function getExcludeListFile() {
    $exclude_file = $this->getConfigValue('deploy.exclude_file');
    $exclude_additions = $this->getConfigValue('deploy.exclude_additions_file');
    if (file_exists($exclude_additions)) {
      $this->say("Combining exclusions from deploy.deploy-exclude-additions and deploy.deploy-exclude files...");
      $exclude_file = $this->mungeExcludeLists($exclude_file, $exclude_additions);
    }

    return $exclude_file;
  }

  /**
   * Utility to merge two line-based files into a temporary file.
   * Removes duplicate lines (including empty lines and lines with only whitespace).
   *
   * @param string $file1
   * @param string $file2
   * @param string|null $outputFile
   *   Optional. If provided, use this as the temp file path.
   * @return string
   *   The filepath to the temporary file containing the combined list.
   * @throws \Exception
   */
  protected function mungeLists(string $file1, string $file2, string $outputFile = null): string {
    $lines = [];
    foreach ([$file1, $file2] as $file) {
      if (file_exists($file)) {
        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
          $line = rtrim($line);
          if ($line !== '') {
            $lines[] = $line;
          }
        }
      }
    }
    $lines = array_unique($lines);
    $outputFile = $outputFile ?: tempnam(sys_get_temp_dir(), 'munge_lists_');
    $result = file_put_contents($outputFile, implode(PHP_EOL, $lines) . PHP_EOL);
    if ($result === false) {
      throw new \Exception("Failed to write merged list to file: $outputFile");
    }
    return $outputFile;
  }

  /**
   * Combines deploy.exclude_file with deploy.exclude_additions_file.
   *
   * Creates a temporary file containing the combination.
   *
   * @return string
   *   The filepath to the temporary file containing the combined list.
   */
  protected function mungeExcludeLists($file1, $file2) {
    $outputFile = $this->excludeFileTemp;
    return $this->mungeLists($file1, $file2, $outputFile);
  }

  /**
   * Combines two gitignore files into a temporary file containing the combination.
   *
   * @param string $file1
   * @param string $file2
   * @param string|null $outputFile
   *   Optional. If provided, use this as the temp file path.
   * @return string
   *   The filepath to the temporary file containing the combined list.
   */
  protected function mungeGitignoreLists($file1, $file2, $outputFile = null) {
    $outputFile = $outputFile ?: $this->deployDir . '/.gitignore.tmp';
    return $this->mungeLists($file1, $file2, $outputFile);
  }

  /**
   * Creates a commit on the artifact.
   *
   * @throws \Exception
   */
  protected function commit() {
    $this->gitConfig();
    $this->say("Committing artifact to <comment>{$this->branchName}</comment>...");
    $result = $this->taskGitStack()
      ->dir($this->deployDir)
      ->exec("git rm -r --cached --ignore-unmatch --quiet .")
      ->add('-A')
      ->commit($this->commitMessage, '--quiet --no-verify')
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE)
      ->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Failed to commit deployment artifact!");
    }
  }

  /**
   * Pushes the artifact to git.remotes.
   *
   * @param string $identifier
   *   Identifier.
   * @param array $options
   *   Options.
   *
   * @return bool
   *   Bool.
   *
   * @throws \Exception
   * @throws \Robo\Exception\TaskException
   */
  protected function push($identifier, array $options) {
    if ($options['dry-run']) {
      $this->logger->warning("Skipping push of deployment artifact. deploy.dryRun is set to true.");
      return FALSE;
    }
    else {
      $this->say("Pushing artifact to git.remotes...");
    }

    $task = $this->taskExecStack()
      ->dir($this->deployDir);
    foreach ($this->getConfigValue('git.remotes') as $remote) {
      $remote_name = md5($remote);
      $task->exec("git push $remote_name $identifier");
    }
    $result = $task->run();

    if (!$result->wasSuccessful()) {
      throw new \Exception("Failed to push deployment artifact!");
    }
  }

  /**
   * Creates a tag on the build repository.
   *
   * @param string $repo
   *   The repo in which a tag should be cut.
   *
   * @throws \Exception
   */
  protected function cutTag($repo = 'build') {
    $this->gitConfig();
    $taskGit = $this->taskGitStack()
      ->tag($this->tagName, $this->commitMessage)
      ->setVerbosityThreshold(VerbosityThresholdInterface::VERBOSITY_VERBOSE);

    if ($repo == 'build') {
      $taskGit->dir($this->deployDir);
    }

    $result = $taskGit->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception("Failed to create Git tag!");
    }
    $this->say("The tag {$this->tagName} was created on the {$repo} repository.");
  }

  /**
   * Ensures git has proper config setup.
   */
  protected function gitConfig() {
    // Check git config before proceeding.
    $gitName = $this->getConfigValue('git.user.name');
    $gitEmail = $this->getConfigValue('git.user.email');

    if (!$gitName || !$gitEmail) {
      throw new \Exception("git.user.name and git.user.email must be set in swat/swat.yml to commit deployment artifact.");
    }
    $gitName = ProcessUtils::escapeArgument($gitName);
    $gitEmail = ProcessUtils::escapeArgument($gitEmail);
    $this->taskGitStack()
      ->exec("config --global user.name $gitName")
      ->exec("config --global user.email $gitEmail")
      ->run();
  }

  /**
   * Update current database to reflect the state of the Drupal file system.
   *
   * @command deploy:drupal
   * @aliases deploy
   *
   * @param array $options
   *  Options that can be passed via the CLI.
   *
   * @throws \Robo\Exception\TaskException
   */
  public function deployDrupal(array $options = ['cache-rebuild' => FALSE, 'uri' => NULL]) {
    $repoRoot = $this->getConfigValue('repo.root');
    $drushOptions = ' --no-interaction --ansi';
    if (!empty($options['uri'])) {
      $drushOptions .= ' --uri=' . escapeshellarg($options['uri']);
    }
    $this->say("Deploying Drupal...");

    // DB updates.
    $task = $this->taskExecStack()->stopOnFail()
      ->dir($repoRoot)
      ->exec('./vendor/bin/drush updb' . $drushOptions);
    $result = $task->run();
    if (!$result->wasSuccessful()) {
      throw new \Exception("Failed to execute database updates!");
    }

    // We import config twice to catch config ignore/split strategies.
    $this->taskExecStack()->stopOnFail()
      ->dir($repoRoot)
      ->exec('./vendor/bin/drush config:import' . $drushOptions)
      ->exec('./vendor/bin/drush config:import' . $drushOptions)
      ->run();

    // Check config status to config import is correct with DB.
    $statusResult = $this->taskExecStack()->stopOnFail()
      ->dir($repoRoot)
      ->exec('./vendor/bin/drush config:status' . $drushOptions)
      ->printMetadata(FALSE)
      ->printOutput(FALSE)
      ->run();
    $message = trim($statusResult->getMessage());
    if ($message != NULL) {
      throw new \Exception("Configuration in the database does not match configuration on disk. This indicates that your configuration on disk needs attention.");
    }

    // Rebuild caches if requested.
    if ($options['cache-rebuild']) {
      $this->taskExecStack()->stopOnFail()
        ->dir($repoRoot)
        ->exec('./vendor/bin/drush cache:rebuild' . $drushOptions)
        ->run();
    }

    // Run drush deploy:hooks to run pending deploy hooks.
    $this->taskExecStack()->stopOnFail()
      ->dir($repoRoot)
      ->exec('./vendor/bin/drush deploy:hook' . $drushOptions)
      ->run();
  }

}
