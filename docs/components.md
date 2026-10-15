# Suggesting Fluid components

TYPO3 14 ships Fluid 5, and with it real components: a template file with typed
`<f:argument>` declarations and an optional `<f:slot />`, called like a tag —
`<my:teaserHeader title="Next dates" />`. Components render in isolation: they see
only what is passed to them. That makes them easier to reason about than Partials,
and it is exactly why not every Partial can become one.

`fluid-lens components` finds the parts of a template base that should become
components, explains why, warns about what a conversion needs, and drafts the
component — without writing a single file.

```bash
vendor/bin/fluid-lens components packages/
```

- [Signals](#signals) — where suggestions come from
- [What stays a Partial](#what-stays-a-partial) — blockers
- [Caveats](#caveats) — what a conversion needs
- [Arguments](#arguments) — names, types, defaults
- [Ranking](#ranking) — score and thresholds
- [Output](#output) — console and JSON
- [Applying a suggestion](#applying-a-suggestion)
- [Baseline](#baseline)
- [Options and configuration](#options-and-configuration)
- [Limits](#limits)

## Signals

Three signals are combined; `--only=partials,clones,similar` picks a subset.

### Partials rendered from several files (`partials`)

Every `<f:render partial="…">` and `{f:render(partial: '…')}` call is resolved to
a scanned Partial file. TYPO3 resolves Partial names through the configured
partial root paths, which a static scan cannot see; fluid-lens prefers the Partial
in the caller's own `Resources/Private/` folder, falls back to the only Partial of
that name, and otherwise reports the call as *ambiguous*, *missing* (rendered from
a vendor extension, for example) or *dynamic* — it never guesses.

A Partial rendered at least `minOccurrences` times from at least `minFiles` files
becomes a suggestion. Its arguments are what it reads:

- arguments it already declares with `f:argument` are taken over unchanged;
- every other free variable becomes an argument — free meaning not defined inside
  the Partial by `f:for as`, `f:variable`, `f:alias` and the like;
- the TypoScript settings a Partial sees implicitly (`{settings.listPageUid}`) become
  explicit arguments (`listPageUid`), and the draft reads them under the new name.

A Partial that only wraps a call of an existing component is skipped — it already
is as reusable as it gets.

Partials no scanned template renders are listed separately. They are usually
overrides of a third-party Partial, rendered by that extension's templates.

### Markup copied into several files (`clones`)

Exact clones in the sense of `analyze`: the same elements, nesting and attribute
names. The copies are lined up node by node and compared:

- an attribute value that differs becomes an argument named after the attribute;
- class names that differ become a `variant` argument, the shared ones stay;
- text that differs becomes `title` (in a heading), `label` (in a link or button)
  or `text` — or, when every copy prints the same property, that property's name;
- when the copies read the same properties of differently named variables
  (`{event.title}` here, `{artist.title}` there), they collapse into one `item`
  object argument;
- local variables are renames, not differences: a loop `as="artist"` in one copy
  and `as="resident"` in the other is the same loop.

Whatever every copy still reads from its surroundings — a variable, a setting —
becomes an argument too, because the component will not see it.

Copies that only share their *shape* are not component material. A suggestion
needs at least `minShared` (default 45 %) of its attribute values and texts to be
the same in every copy, at least two plain HTML elements (Fluid control flow on its
own, or a wrapper around render calls, does not count), and no more than
`maxArguments` arguments. Repetition inside a single file is a loop, not a
component, and is skipped by `minFiles`.

### Similar markup (`similar`)

Near-duplicates in the sense of `similar` (pq-gram similarity at least
`threshold`). Their children are matched as a longest common subsequence by tag
name, so an element added or removed in one copy does not shift the rest:

- an element some copies simply leave out becomes **optional**: it is wrapped in
  `<f:if condition="{showIcon}">` and a boolean argument defaults to what most
  copies do;
- a region where the copies diverge otherwise becomes the component's
  **`<f:slot />`**; each call carries its copy's content between the tags.
  Fluid components have one default slot, so all diverging regions must share an
  element below the root — copies that differ right at the top are too different
  to be one component.

When the copies are the entire content of several Partials — the same frame kept
in one Partial per extension — the suggestion becomes a **merge**: replace each
Partial's content with the component call (or inline the call at the render
calls). A merge is weighted by how often those Partials are rendered, and it
replaces the suggestions for each of them on its own.

### Overlaps and existing components

The signals overlap: a Partial's body is also copied markup, a copied block can sit
inside a bigger one. Candidates are taken best first; one whose every occurrence
lies inside markup a kept candidate already covers is dropped.

Component definitions — files below a `Components/` folder — are never suggested,
and a suggestion whose markup looks like an existing component (pq-gram similarity
of at least 0.8) points at it: use that one instead.

## What stays a Partial

Some Partials rely on their caller's context in a way a component cannot. They are
listed under *Kept as Partials* with the reason:

| Code | Why |
|------|-----|
| `form-context` | Uses `f:form.*` fields without their `<f:form>`. A component has its own ViewHelper variable container, so the fields would lose the surrounding form. A component containing the whole `<f:form>` is fine. |
| `caller-section` | Renders a section of the calling template (`<f:render section="…">` with no matching `f:section` of its own). |
| `layout` | Lives in `Layouts/` or uses `f:layout`. |
| `template-section` | The markup is an `f:section` itself. |

## Caveats

A caveat does not block a suggestion; it names what the conversion needs:

| Code | Meaning |
|------|---------|
| `implicit-settings` | The markup reads `{settings.*}`. Partials see the settings implicitly; the component gets them as arguments, and every call has to pass them. |
| `callers-pass-all` | Callers render the Partial with `arguments="{_all}"`. The arguments are read from the Partial's body, so check them at those call sites. |
| `callers-disagree` | Callers pass different arguments; those not passed everywhere are proposed as optional. |
| `forwards-all` | The markup forwards `{_all}` to another Partial. Inside a component that is only the component's own arguments. |
| `partial-sections` | Some callers render a single section of the Partial; a component has no sections. |
| `default-unknown` | Some copies lack an attribute. An empty value may not behave like a missing attribute — check it. |
| `too-configurable` | More arguments than `maxArguments`: a component this configurable is often two. |
| `no-collection` | The project has no component collection yet; `my:` is a placeholder prefix. |

## Arguments

**Types** are inferred conservatively, so a draft never rejects a valid value:

- a variable used as a condition is `bool`, one looped over is `array`, one whose
  properties are read is `object`;
- values seen at the call sites or in the copies: integers are `int` (`0`/`1` only
  are `bool`), decimals `float`, `{true}`/`{false}` `bool`, array literals `array`,
  other literals `string`;
- a value passed as a variable from the caller stays `mixed` — its type is not
  visible statically.

Declared `f:argument`s keep their type exactly as written, class names included.
Fluid 5 converts scalars before checking the type, so `string`, `int` and `bool`
accept what callers usually pass.

**Optional and default**: an argument is optional when some copy lacks it, or when
a caller whose arguments are known does not pass it. Optional strings default to
`""`, optional booleans to `{false}` (or `{true}` for an element most copies show);
a value equal to the default is left out of the calls.

**Names** follow the source (attribute names, property names, the Partial's
variable names). A name already taken gets the role of its element in front — a
second `variant` on a link becomes `linkVariant` — and only then a number. `_all`
is reserved.

**Component names** come from the Partial path (`Event/Teaser/Header` becomes
`TeaserHeader`; the parent segment is added while the last one alone says nothing,
and more segments are used when a name is taken), or from the shared part of the
root's class names (`events-teasers-header` and `artists-teasers-header` give
`TeasersHeader`), or from the first named block inside (`ArtistSingleList` for a
loop over `.artist-single`), or from the root's role. The tag uses the prefix most
existing component calls use.

## Ranking

```
score = call sites × log2(1 + elements) × spread × argument factor × adjustments
```

- **spread**: `1 + 0.5` per additional file, at most `2` — copies in many files
  drift apart;
- **argument factor**: `1 − 0.05` per argument, `0.5` above `maxArguments`;
- **adjustments**: `× 1.3` when a Partial already declares its arguments, `× 1.2`
  when callers span several extensions, `× 0.8` for implicit settings, and the
  shared share of content for copied and similar markup.

The breakdown is part of the JSON output (`scoreFactors`). Priority is *high* from
12, *medium* from 6, *low* below. Candidates below the thresholds are counted as
*repetitions below the thresholds* in the summary.

## Output

The console report shows, per candidate: name, tag, score and priority; the signal,
call sites, files and size; *Why*; *Caveats*; the argument table (name, type,
optional, default, origin, values seen); the slot, if any; the draft with its
suggested path; the call for the first occurrence; and all occurrences. After the
candidates: what stays a Partial, the existing components, Partials without a
render call, and render calls that resolve to no scanned file.

`--limit` caps the candidates shown (default 20), `--no-draft` hides the drafts.

`--json` contains everything, including every call:

```json
{
  "filesScanned": 179,
  "candidateCount": 38,
  "candidates": [
    {
      "id": "merge:…/Event/Teaser/Header.html,…/Product/Teaser/Header.html",
      "name": "TeasersHeader",
      "tag": "st:teasersHeader",
      "score": 6.0,
      "priority": "medium",
      "scoreFactors": { "call sites": 3.0, "size": 3.0, "spread": 2.0, "arguments": 0.7, "shared content": 0.48 },
      "sources": ["similar"],
      "reasons": ["…"],
      "caveats": [{ "code": "implicit-settings", "message": "…" }],
      "arguments": [{ "name": "showIcon", "type": "bool", "optional": true, "default": "{true}", "origin": "presence", "observedValues": ["present in 1 of 2"] }],
      "slot": null,
      "draft": { "path": "…/Components/TeasersHeader/TeasersHeader.fluid.html", "source": "<html …" },
      "occurrences": [{ "file": "…/Event/Teaser/Header.html", "line": 2, "call": "<st:teasersHeader … />" }],
      "partial": { "names": ["Event/Teaser/Header", "Product/Teaser/Header"], "files": ["…"] },
      "existingMatch": null
    }
  ],
  "keptAsPartials": [{ "name": "Cart/OrderForm/Address/Field", "file": "…", "callSites": 16, "reasons": [{ "code": "form-context", "message": "…" }] }],
  "existingComponents": [{ "name": "icon", "file": "…", "usages": 28 }],
  "unreferencedPartials": [{ "name": "Product/Price", "file": "…" }],
  "unresolvedPartials": [{ "name": "Mail/Footer", "reason": "missing", "callers": ["…:12"] }],
  "skippedLowPayoff": 166
}
```

## Applying a suggestion

1. **Register a component collection** if the project has none. In TYPO3 14.1+
   that is configuration, no PHP class needed —
   `EXT:my_site/Configuration/Fluid/ComponentCollections.php`:

   ```php
   <?php

   return [
       'MyVendor\\MySite\\Components' => [
           'templatePaths' => [10 => 'EXT:my_site/Resources/Private/Components'],
       ],
   ];
   ```

   Templates import it with
   `xmlns:my="http://typo3.org/ns/MyVendor/MySite/Components"` or
   `{namespace my=MyVendor\MySite\Components}`.

2. **Copy the draft** to the suggested path and review it: the argument names and
   types, the caveats, and the markup — whitespace between inline elements is not
   preserved by the parser, so compare the rendered output.
3. **Replace each occurrence** with its call. For a Partial, replace the render
   calls (or, for a merge, each Partial's content); for copied markup, the copied
   block.
4. Run `fluid-lens components` again: the suggestion is gone, and
   `fluid-lens analyze` shows one duplicated structure less.

## Baseline

```bash
vendor/bin/fluid-lens components packages/ --generate-baseline
vendor/bin/fluid-lens components packages/ --baseline=fluid-lens-components-baseline.json
```

A suggestion is identified by the Partial file, the structure of the copied
markup, or the merged Partial files, together with its number of call sites. It
stays quiet while it does not grow; one more copy or render call brings it back.
Like the other commands, `components` exits with `1` while there are suggestions.

## Options and configuration

| Option | Config key (`components`) | Default | Meaning |
|--------|---------------------------|---------|---------|
| `--only` | `only` | all | Signals: `partials`, `clones`, `similar` |
| `--min-occurrences` | `minOccurrences` | 2 | Call sites (render calls or copies) a candidate needs |
| `--min-files` | `minFiles` | 2 | Files the call sites must span |
| `--min-elements` | `minElements` | 3 | Minimum size of copied markup |
| `--max-arguments` | `maxArguments` | 6 | Partial suggestions above this get a caveat; copied markup above it is not suggested |
| `--threshold` | `threshold` | 0.8 | pq-gram similarity for similar markup |
| `--min-shared` | `minShared` | 0.45 | Share of values and texts copies must have in common |
| — | `prefixes` | detected | Component namespace prefixes besides those detected from `xmlns`/`{namespace}` declarations ending in `\Components` |
| `--baseline` | `baseline` | — | Suppress suggestions listed in this baseline |
| `--generate-baseline` | — | — | Write the current suggestions to a baseline and exit |
| `--limit` | — | 20 | Candidates shown in the console report |
| `--no-draft` | — | — | Hide the drafts in the console report |
| `--json` | — | — | JSON output |

Plus the options every scanning command has: a path argument (or the configured
`paths`), `--exclude-path`, `--config`. In TYPO3 the command is also available as
`vendor/bin/typo3 fluidlens:components`.

## Limits

fluid-lens reads markup; it does not run Fluid. That keeps it fast and independent
of a TYPO3 instance, and it means:

- **Variables are read with patterns.** Complex inline syntax can be read too
  widely; the scanner errs on the side of reporting a variable, because a missed
  input breaks a component silently while an extra one is visible in the draft.
- **Partial resolution ignores TypoScript.** Partial root paths, their order and
  vendor overrides are invisible; ambiguous and missing names are reported, never
  guessed.
- **One slot.** Fluid components have one default slot, so copies that diverge in
  several places without a common element below the root are not suggested.
- **Siblings, not subtrees.** Duplication that is a run of sibling nodes (address
  lines separated by `<br>`) is invisible to the element-based detectors; it is
  found only when it lives in a Partial.
- **Small trees.** pq-gram similarity of small blocks drops quickly when one
  element is missing; lower `--threshold` to see more similar markup.
- **Drafts are starting points.** Whitespace between inline elements and argument
  defaults the source does not state are reviewed by a person, not decided by the
  tool.
