<?php

/*
 * This file is part of the Sismo utility.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace Sismo;

use Symfony\Component\Process\Process;

// @codeCoverageIgnoreStart
/**
 * Builds commits.
 *
 * @author Alex Slubsky <aslubsky@gmail.com>
 */
class SvnBuilder
{
    private $project;
    private $baseBuildDir;
    private $buildDir;
    private $callback;
    private $svnPath;
    private $svnCmds;

    public function __construct($buildDir, $svnPath, array $svnCmds)
    {
        $this->baseBuildDir = $buildDir;
        $this->svnPath = $svnPath;

        $this->svnCmds = array_replace(array(
            'checkout' => 'co %repo% %dir%',
            'update'    => 'up %dir%',
            'info'     => 'info',
        ), $svnCmds);
    }

    public function init(Project $project, $callback = null)
    {
        $this->project  = $project;
        $this->callback = $callback;
        $this->buildDir = $this->baseBuildDir.'/'.$this->getBuildDir($project);
    }

    public function build()
    {
        file_put_contents($this->buildDir.'/sismo-run-tests.sh', str_replace(array("\r\n", "\r"), "\n", $this->project->getCommand()));

        $process = new Process('sh sismo-run-tests.sh', $this->buildDir);
        $process->setTimeout(3600);
        $process->run($this->callback);

        return $process;
    }

    public function getBuildDir(Project $project)
    {
        return substr(md5($project->getRepository().$project->getBranch()), 0, 6);
    }

    public function prepare($revision, $sync)
    {
        if (!file_exists($this->buildDir)) {
            mkdir($this->buildDir, 0777, true);
        }

        if (!file_exists($this->buildDir.'/.svn')) {
            $this->execute($this->getSvnCommand('checkout'), sprintf('Unable to checkout repository for project "%s".', $this->project));
        }

        if ($sync) {
            $this->execute($this->getSvnCommand('update'), sprintf('Unable to update repository for project "%s".', $this->project));
        }

        $process = $this->execute($this->getSvnCommand('info'), sprintf('Unable to get logs for project "%s".', $this->project));

        $outParts = explode("\n", trim($process->getOutput()));
        $outParts[4] = trim(end(explode(':', $outParts[4])));
        $outParts[7] = trim(end(explode(':', $outParts[7])));
        $outParts[9] = trim(end(explode(': ', $outParts[9])));
        $outParts[9] = trim(current(explode('(', $outParts[9])));

        return array(
            $outParts[4],
            $outParts[7],
            $outParts[9],
            $outParts[7]
        );
    }

    protected function getSvnCommand($command, array $replace = array())
    {
        $replace = array_merge(array(
            '%repo%'        => escapeshellarg($this->project->getRepository()),
            '%dir%'         => escapeshellarg($this->buildDir)
        ), $replace);

        return strtr($this->svnPath.' '.$this->svnCmds[$command], $replace);
    }

    private function execute($command, $message)
    {
        if (null !== $this->callback) {
            call_user_func($this->callback, 'out', sprintf("Running \"%s\"\n", $command));
        }
        $process = new Process($command, $this->buildDir);
        $process->setTimeout(3600);
        $process->run($this->callback);
        if (!$process->isSuccessful()) {
            throw new BuildException($message);
        }

        return $process;
    }
}
// @codeCoverageIgnoreEnd
