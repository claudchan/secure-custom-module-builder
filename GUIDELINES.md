# Global Engineering Standards

These standards apply to all repositories and workspaces unless a higher-priority instruction or explicit project requirement says otherwise.

## 1. Engineering Principles

* Prioritize correctness, quality, simplicity, robustness, scalability, security, accessibility, and long-term maintainability over development speed.
* Prefer the simplest solution that fully satisfies the requirements.
* Preserve existing architecture, conventions, and patterns unless there is a clear reason to change them.
* Do not introduce unnecessary dependencies, abstractions, frameworks, or architectural complexity.
* Do not silently expand the requirements of a task.
* Do not make speculative improvements simply because they appear possible.
* When multiple approaches are reasonable, prefer the approach with the best balance of maintainability, reliability, simplicity, and compatibility.
* Reuse existing project functionality and patterns before introducing new solutions.

## 2. Agent Efficiency and Scope

* Spend enough time investigating and reasoning to produce a high-quality result.
* Do not optimize for minimum token usage at the expense of correctness or quality.
* Optimize for useful work: avoid unnecessary exploration, repetition, speculation, and unrelated investigation.
* Start with the smallest set of files and information likely to be relevant to the requested task.
* Use targeted file references when available.
* Do not scan the entire repository unless the task genuinely requires repository-wide understanding.
* Do not repeatedly inspect the same files unless new evidence requires it.
* Stop investigating once there is sufficient evidence to make a reliable decision.
* Do not continue searching merely to eliminate every possible uncertainty.
* If important information is genuinely missing, state what is missing and ask for clarification rather than guessing or investigating indefinitely.
* Do not refactor unrelated code.
* Do not clean up unrelated code merely because it is encountered.
* Do not modify unrelated files unless they are required to complete or safely verify the requested change.
* Keep implementation plans focused on the requested scope.
* For simple tasks, proceed directly without unnecessary planning.
* For complex tasks, use an Explore -> Plan -> Execute -> Verify workflow.

## 3. Requirements and Scope Control

* Follow the user's explicit requirements precisely.
* Do not invent requirements, business rules, design decisions, or expected behaviour.
* Do not add features that were not requested unless they are necessary for correctness, security, accessibility, compatibility, or the requested functionality.
* If an additional change would materially expand the scope, explain it before proceeding when practical.
* If there are multiple reasonable interpretations of a requirement, identify the ambiguity rather than silently choosing a potentially incorrect interpretation.
* Prefer minimal scope with complete correctness over broad scope with unnecessary changes.

## 4. Code Changes

* Make the smallest safe change that fully solves the problem.
* Preserve existing public APIs, interfaces, data structures, naming conventions, and behaviour unless the task requires changing them.
* Do not rename, reorganize, or restructure code without a clear reason.
* Do not introduce a new abstraction when the existing architecture can reasonably support the requirement.
* Do not change formatting in unrelated files.
* Do not modify generated files manually.
* Do not modify third-party or vendor code unless explicitly instructed.
* Do not modify framework or platform core files unless explicitly required.
* Consider backwards compatibility when changing existing functionality.
* Consider security, accessibility, performance, responsive behaviour, and error handling where relevant to the change.

## 5. Bug Fixing

* First understand and reproduce the problem when practical.
* Reproduce bugs at the lowest appropriate level that reliably demonstrates the failure.
* Use end-to-end testing when the problem involves user-facing behaviour, browser behaviour, system integration, or interactions that cannot be reliably verified at a lower level.
* Identify the root cause before implementing a fix.
* Do not treat symptoms as the root cause when the underlying cause can reasonably be determined.
* Make the smallest reliable fix that addresses the root cause.
* Verify that the original problem is resolved.
* Verify that the fix does not introduce regressions in the affected area.
* Do not use a bug fix as an excuse for unrelated refactoring.

## 6. Testing and Verification

* Verify changes rather than assuming they work.
* Run the smallest appropriate set of tests, checks, linting, builds, or browser verification necessary to establish confidence in the change.
* For user-facing UI changes, verify responsive behaviour, layout, typography, spacing, states, interactions, accessibility, and visual consistency where relevant.
* When a reference design or specification exists, aim for pixel-level visual accuracy.
* Fix test, lint, or build failures caused by the current change.
* Distinguish pre-existing failures from failures introduced by the current change.
* Do not expand the task to fix unrelated pre-existing failures unless explicitly requested or they prevent reliable verification.
* Do not run broad or expensive checks when a targeted check can provide sufficient confidence.
* If full verification cannot be performed, clearly state what was and was not verified.

## 7. Unrelated Issues

* If an unrelated issue is discovered, do not automatically fix it.
* Fix an unrelated issue only when it directly affects the requested change or represents a significant correctness, security, accessibility, or compatibility problem.
* Otherwise, mention notable unrelated issues separately without modifying them.
* Keep the current task's diff focused and reviewable.

## 8. Git and Repository Hygiene

* Keep changes focused on the requested task.
* Review the final diff before considering the task complete.
* Do not accidentally include unrelated modifications.
* Never automatically add the AI agent's name as a co-author to commit messages.
* Never create commits unless explicitly requested.
* Never manually modify `CHANGELOG.md` when it is generated automatically.
* Never manually modify other generated or machine-managed files unless explicitly required.
* Preserve existing Git conventions in the repository.

## 9. Markdown Standards

* When editing Markdown, place each complete sentence on its own physical line.
* Preserve normal Markdown structure, including headings, lists, tables, code blocks, links, and formatting.
* Do not place multiple complete sentences on the same physical line outside code blocks.
* Do not unnecessarily reformat unrelated Markdown content.
* Never use the em dash character `—`.
* Use the plain hyphen `-` instead.

## 10. Communication

* Be concise but sufficiently detailed to communicate important reasoning, decisions, risks, and verification results.
* Do not provide long explanations when a short explanation is sufficient.
* Do not hide important uncertainty behind confident language.
* When reporting completed work, summarize:

  * What changed.
  * Which files changed.
  * What was verified.
  * Any remaining limitations or concerns.
* When blocked, clearly explain the blocker and the minimum information needed to proceed.
* Do not claim that something was tested, verified, executed, or reviewed if it was not actually done.

## 11. Decision Making

When deciding how to approach a task:

1. Understand the requested outcome.
2. Identify the smallest relevant scope.
3. Inspect the existing implementation and conventions.
4. Determine the root cause or appropriate implementation approach.
5. Choose the simplest robust solution.
6. Implement only the required changes.
7. Verify the result appropriately.
8. Review the final diff for unintended changes.
9. Stop when the requested work is complete.

The goal is not to do the most work possible.

The goal is to produce the best reliable result with the least unnecessary work.

## 12. Rule Conflicts

* Follow these standards by default.
* Follow higher-priority system, platform, repository, or explicit user instructions when they conflict with these standards.
* If an explicit instruction intentionally overrides one of these standards, follow the explicit instruction.
* Do not use these standards as a reason to refuse a legitimate requested change.
