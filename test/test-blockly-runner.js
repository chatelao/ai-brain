const { spawnSync } = require('child_process');

/**
 * Unit Test for blockly-runner.js
 */

const testCases = [
    {
        name: 'Basic Notification',
        input: {
            code: 'onEvent("ISSUE_LABELED", (event) => { notify("Issue was labeled!"); });',
            context: {
                event: { type: 'ISSUE_LABELED' }
            }
        },
        expectedAction: { type: 'notify', message: 'Issue was labeled!' }
    },
    {
        name: 'Read Label Predicate (True)',
        input: {
            code: 'onEvent("ISSUE_CLOSED", (event) => { if (readLabel("bug")) { notify("Bug closed"); } });',
            context: {
                event: { type: 'ISSUE_CLOSED' },
                task: { labels: [{ name: 'bug' }] }
            }
        },
        expectedAction: { type: 'notify', message: 'Bug closed' }
    },
    {
        name: 'Read Label Predicate (False)',
        input: {
            code: 'onEvent("ISSUE_CLOSED", (event) => { if (readLabel("feature")) { notify("Feature closed"); } });',
            context: {
                event: { type: 'ISSUE_CLOSED' },
                task: { labels: [{ name: 'bug' }] }
            }
        },
        expectedAction: null
    },
    {
        name: 'Is Task Ready Predicate',
        input: {
            code: 'onEvent("CHECKS_COMPLETED", (event) => { if (isTaskReady()) { merge(); } });',
            context: {
                event: { type: 'CHECKS_COMPLETED' },
                task: { status: 'ready' }
            }
        },
        expectedAction: { type: 'merge' }
    },
    {
        name: 'Multiple Actions',
        input: {
            code: 'onEvent("PR_MERGED", (event) => { setLabel("merged"); postComment("Great work!"); });',
            context: {
                event: { type: 'PR_MERGED' }
            }
        },
        expectedActions: [
            { type: 'setLabel', label: 'merged' },
            { type: 'postComment', comment: 'Great work!' }
        ]
    },
    {
        name: 'Timeout Protection',
        input: {
            code: 'onEvent("ISSUE_OPENED", (event) => { while(true) {} });',
            context: {
                event: { type: 'ISSUE_OPENED' }
            }
        },
        expectFailure: true
    }
];

let failed = false;

testCases.forEach(test => {
    const child = spawnSync('node', ['scripts/blockly-runner.js'], {
        input: JSON.stringify(test.input),
        encoding: 'utf8'
    });

    if (child.error) {
        console.error(`FAIL: ${test.name} - Child process error: ${child.error}`);
        failed = true;
        return;
    }

    try {
        const output = JSON.parse(child.stdout);

        if (test.expectFailure) {
            if (output.success) {
                console.error(`FAIL: ${test.name} - Expected failure but got success`);
                failed = true;
            } else {
                console.log(`PASS: ${test.name} (Caught expected error: ${output.error})`);
            }
            return;
        }

        if (!output.success) {
            console.error(`FAIL: ${test.name} - Script failed: ${output.error}`);
            failed = true;
            return;
        }

        if (test.expectedAction) {
            const hasAction = output.actions.some(a => JSON.stringify(a) === JSON.stringify(test.expectedAction));
            if (hasAction) {
                console.log(`PASS: ${test.name}`);
            } else {
                console.error(`FAIL: ${test.name} - Expected action not found. Output: ${JSON.stringify(output.actions)}`);
                failed = true;
            }
        } else if (test.expectedActions) {
            const allFound = test.expectedActions.every(ea =>
                output.actions.some(a => JSON.stringify(a) === JSON.stringify(ea))
            );
            if (allFound && output.actions.length === test.expectedActions.length) {
                console.log(`PASS: ${test.name}`);
            } else {
                console.error(`FAIL: ${test.name} - Expected actions mismatch. Output: ${JSON.stringify(output.actions)}`);
                failed = true;
            }
        } else {
            if (output.actions.length === 0) {
                console.log(`PASS: ${test.name}`);
            } else {
                console.error(`FAIL: ${test.name} - Expected no actions but got: ${JSON.stringify(output.actions)}`);
                failed = true;
            }
        }

    } catch (err) {
        console.error(`FAIL: ${test.name} - Error parsing output: ${err.message}`);
        console.error(`STDOUT: ${child.stdout}`);
        failed = true;
    }
});

if (failed) {
    process.exit(1);
} else {
    console.log('\nAll blockly-runner tests passed!');
}
