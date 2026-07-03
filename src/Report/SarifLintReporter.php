<?php

declare(strict_types=1);

namespace YellowTwins\FluidLens\Report;

use YellowTwins\FluidLens\Rule\Finding;
use YellowTwins\FluidLens\Rule\RuleCatalog;
use YellowTwins\FluidLens\Rule\Severity;

/**
 * Renders lint findings as SARIF 2.1.0, the format GitHub code scanning consumes,
 * so findings can appear inline on pull requests and in the Security tab.
 *
 * @see https://docs.github.com/code-security/code-scanning/integrating-with-code-scanning/sarif-support-for-code-scanning
 */
final class SarifLintReporter
{
    private const SCHEMA = 'https://json.schemastore.org/sarif-2.1.0.json';
    private const TOOL_URI = 'https://github.com/yellow-twins/fluid-lens';

    public function __construct(
        private readonly string $version = '0.1.0',
    ) {
    }

    /**
     * @param list<Finding> $findings
     */
    public function render(array $findings): string
    {
        $document = [
            'version' => '2.1.0',
            '$schema' => self::SCHEMA,
            'runs' => [
                [
                    'tool' => [
                        'driver' => [
                            'name' => 'fluid-lens',
                            'informationUri' => self::TOOL_URI,
                            'version' => $this->version,
                            'rules' => $this->rules($findings),
                        ],
                    ],
                    'results' => array_map(fn (Finding $finding): array => $this->result($finding), $findings),
                ],
            ],
        ];

        return json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param list<Finding> $findings
     *
     * @return list<array<string, mixed>>
     */
    private function rules(array $findings): array
    {
        $rules = [];
        foreach ($findings as $finding) {
            if (isset($rules[$finding->rule])) {
                continue;
            }

            $rule = ['id' => $finding->rule, 'name' => $finding->rule];
            $description = RuleCatalog::describe($finding->rule);
            if ($description !== null) {
                $rule['shortDescription'] = ['text' => $description];
            }

            $rules[$finding->rule] = $rule;
        }

        return array_values($rules);
    }

    /**
     * @return array<string, mixed>
     */
    private function result(Finding $finding): array
    {
        $result = [
            'ruleId' => $finding->rule,
            'level' => $this->level($finding->severity),
            'message' => ['text' => $finding->message],
            'locations' => [
                [
                    'physicalLocation' => [
                        'artifactLocation' => ['uri' => $finding->file],
                        'region' => ['startLine' => max(1, $finding->line)],
                    ],
                ],
            ],
        ];

        if ($finding->reference !== null) {
            $result['properties'] = ['reference' => $finding->reference];
        }

        return $result;
    }

    private function level(Severity $severity): string
    {
        return match ($severity) {
            Severity::Error => 'error',
            Severity::Warning => 'warning',
            Severity::Notice => 'note',
        };
    }
}
