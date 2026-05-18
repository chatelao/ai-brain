import os
import re

def fix_file(filepath):
    with open(filepath, 'r') as f:
        content = f.read()

    # Normalize states
    content = content.replace("'pending'", "'CREATED'")
    content = content.replace("'in_progress'", "'PROCESSING'")
    content = content.replace("'completed'", "'FINISHED'")
    content = content.replace("'failed'", "'FAILED'")

    # Ensure only one substatus column
    content = re.sub(r',\s*substatus (?:VARCHAR\(50\)|TEXT)', '', content)
    content = content.replace('status VARCHAR(50)', 'status VARCHAR(50), substatus VARCHAR(50)')
    content = content.replace('status TEXT', 'status TEXT, substatus TEXT')

    # Fix TaskFilteringTest
    if "TaskFilteringTest.php" in filepath:
        # The actual values in DB must match Expected in test
        content = content.replace("'FINISHED'", "'FINISHED'")

    with open(filepath, 'w') as f:
        f.write(content)

directory = 'test/Integration'
for filename in os.listdir(directory):
    if filename.endswith('.php'):
        fix_file(os.path.join(directory, filename))
