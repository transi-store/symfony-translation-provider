<?php

declare(strict_types=1);

namespace TransiStore\TranslationProvider;

use Symfony\Component\Process\Process;

/**
 * Detects the current git branch to automatically scope translation API calls.
 *
 * Returns null when:
 *  - the working directory is not inside a git repository;
 *  - the repository is in detached HEAD state;
 *  - the current branch is the default branch (main or master).
 */
final class GitBranchResolver
{
    private const MAIN_BRANCHES = ['main', 'master'];

    public function resolve(): ?string
    {
        if (!$this->isGitRepository()) {
            return null;
        }

        $process = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD']);
        $process->run();

        if (!$process->isSuccessful()) {
            return null;
        }

        $branch = trim($process->getOutput());

        if (!$branch || 'HEAD' === $branch) {
            return null;
        }

        if (\in_array($branch, self::MAIN_BRANCHES, true)) {
            return null;
        }

        return $branch;
    }

    private function isGitRepository(): bool
    {
        $process = new Process(['git', 'rev-parse', '--is-inside-work-tree']);
        $process->run();

        return $process->isSuccessful();
    }
}
