"""Classify Roave's structured result, keeping skipped checks infrastructure RED."""
import json
import sys


def classify(exit_code: int, output: str) -> str:
    if exit_code not in (0, 3):
        return 'infrastructure'
    for line in reversed(output.splitlines()):
        try:
            report = json.loads(line)
        except json.JSONDecodeError:
            continue
        if not isinstance(report, dict) or 'errors' not in report:
            continue
        errors = report['errors']
        if not isinstance(errors, list):
            return 'infrastructure'
        allowed = {'added', 'changed', 'removed'}
        if any(not isinstance(row, dict) or row.get('modificationType') not in allowed for row in errors):
            return 'infrastructure'
        if exit_code == 0 and not errors:
            return 'compatible'
        if exit_code == 3 and errors:
            return 'bc-break'
        return 'infrastructure'
    return 'infrastructure'


if __name__ == '__main__':
    print(classify(int(sys.argv[1]), sys.stdin.read()))
