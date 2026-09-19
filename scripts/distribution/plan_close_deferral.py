"""D44/D45 plan-close deferral guards for distribution helpers."""
from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
FLAG = ROOT / 'scripts/.plan-close-deferred'


class DeferralDenied(RuntimeError):
    """Remote or CI carrier blocked until terminal PLAN-CLOSE-CI."""


def active() -> bool:
    return FLAG.is_file()


def assert_flag_valid() -> None:
    if active() and 'PLAN-CLOSE-CI' not in FLAG.read_text(encoding='utf-8'):
        raise DeferralDenied('plan-close deferral flag present but malformed')


def refuse_remote(carrier: str, *, resume: str = '--finalize on cached receipts') -> None:
    """Block network/gh/CI carriers while scripts/.plan-close-deferred exists."""
    if not active():
        return
    assert_flag_valid()
    raise DeferralDenied(
        f'{carrier} deferred (D44/D45): remote/CI only at PLAN-CLOSE-CI; use {resume}'
    )
