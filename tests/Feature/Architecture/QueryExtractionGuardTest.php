<?php

namespace Tests\Feature\Architecture;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class QueryExtractionGuardTest extends TestCase
{
    /**
     * @var array<int, string>
     */
    private const FORBIDDEN_TOKENS = [
        'DB::table(',
        '::query(',
    ];

    /**
     * Ensure service-layer classes do not contain inline query entrypoints.
     *
     * Logic:
     * 1) Scan all service PHP files for forbidden query tokens.
     * 2) Fail the test when at least one violation is found.
     *
     * @return void
     */
    public function test_services_do_not_contain_inline_query_calls(): void
    {
        $violations = $this->collectViolations(base_path('app/Services'));

        $this->assertSame([], $violations, "Inline query calls detected in service layer:\n".implode("\n", $violations));
    }

    /**
     * Ensure controller-layer classes do not contain inline query entrypoints.
     *
     * Logic:
     * 1) Scan all controller PHP files for forbidden query tokens.
     * 2) Fail the test when at least one violation is found.
     *
     * @return void
     */
    public function test_controllers_do_not_contain_inline_query_calls(): void
    {
        $violations = $this->collectViolations(base_path('app/Http/Controllers'));

        $this->assertSame([], $violations, "Inline query calls detected in controller layer:\n".implode("\n", $violations));
    }

    /**
        * Collect file-level forbidden-token matches within one directory.
        *
        * Logic:
        * 1) Read all files under the provided directory.
        * 2) Check each file for all forbidden query tokens.
        * 3) Record matches with relative file path and token.
        * 4) Return a sorted violations list for deterministic assertions.
        *
        * @param  string  $directoryPath
     * @return array<int, string>
     */
    private function collectViolations(string $directoryPath): array
    {
        $violations = [];

        foreach (File::allFiles($directoryPath) as $file) {
            $contents = File::get($file->getPathname());

            foreach (self::FORBIDDEN_TOKENS as $token) {
                if (str_contains($contents, $token)) {
                    $violations[] = sprintf('%s contains %s', $file->getRelativePathname(), $token);
                }
            }
        }

        sort($violations);

        return $violations;
    }
}
