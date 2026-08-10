<?php

namespace OTGH\AccessControl\Core\Services\Supervisor;

class SupervisorProgramRegistry
{
    /**
     * @var array<int,callable(string,string):array<int,string>>
     */
    private array $contributors = [];

    /**
     * @param  callable(string,string):array<int,string>  $contributor
     */
    public function register(callable $contributor): void
    {
        $this->contributors[] = $contributor;
    }

    /**
     * @return array<int,string>
     */
    public function renderSections(string $phpBinary, string $workingDir): array
    {
        $sections = [];

        foreach ($this->contributors as $contributor) {
            $result = $contributor($phpBinary, $workingDir);

            if (! is_array($result)) {
                continue;
            }

            foreach ($result as $section) {
                $section = trim((string) $section);
                if ($section !== '') {
                    $sections[] = $section;
                }
            }
        }

        return $sections;
    }
}
