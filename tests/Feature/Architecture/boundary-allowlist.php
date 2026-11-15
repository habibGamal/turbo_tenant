<?php

declare(strict_types=1);

/*
 * Boundary allow-list. Phase 5 emptied every debt entry. The only entry left is a
 * permanent exception: the plugin scaffolder generates code inside App\Plugins\...
 * Do not add new entries — fix the dependency instead (see the rules at the top of
 * PluginBoundariesArchTest.php).
 */
return [
    'core' => [],

    'text' => [
        'D1a' => [],
        'D1b' => [],
        'D1b-redundant' => [],
        'D1c' => [],

        'D2' => [
            // PERMANENT (not debt): the plugin scaffolder writes App\Plugins\... namespaces into generated stubs.
            'app/Console/Commands/MakePluginCommand.php',
        ],
    ],
];
