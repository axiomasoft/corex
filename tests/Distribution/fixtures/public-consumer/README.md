# Public distribution consumer fixture

`pristine-proof.py` creates this fixture in a fresh temporary root and adds a
single local Composer repository containing sealed ZIP dist artifacts. It must
never receive a path repository, auth credentials, a source mount, vendor
directory, or a sibling package checkout.
