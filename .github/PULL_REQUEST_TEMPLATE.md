## What this changes

<!-- What does this do, and why is it needed? -->

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation
- [ ] Maintenance / tooling

## Checks

- [ ] `composer run syntax` passes
- [ ] `composer run lint` passes with no errors and no warnings
- [ ] `composer run test` passes
- [ ] Added or updated tests for the behaviour this changes
- [ ] Updated `CHANGELOG.md`
- [ ] Updated `readme.txt` and `docs/` if user-facing behaviour changed

## If this touches the API layer

- [ ] New endpoints are registered in `src/Api/Endpoints.php` with their required Procore permission
- [ ] `'public' => true` is only set on endpoints whose payload is safe to publish
- [ ] Output goes through `Format::cell()` or an explicit `esc_*` call
- [ ] Procore error messages are not exposed to non-administrators

## Testing done

<!-- What did you verify, and how? Note if you tested against a real Procore account. -->
