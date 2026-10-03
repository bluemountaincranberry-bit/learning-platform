<?php

namespace App\Modules\Ai\Application\Prompt;

use InvalidArgumentException;

/**
 * `{{variable}}` substitution plus a minimal `{{#if var}}...{{else}}...{{/if}}`
 * conditional block, for a prompt template's text — kept as its own
 * single-responsibility class rather than a method on
 * PromptRegistryService so it can be unit-tested without touching
 * Eloquent, and reused later by the builder canvas's "test run" action
 * (renders exactly what a real call would send, before anything is
 * published).
 *
 * The conditional block exists because several of this codebase's real
 * prompts are assembled with real branching (a target-level clause only
 * when one is set, a "be thorough" vs "stay focused" instruction depending
 * on a flag — see AiContentAnalysisService::buildSystemPrompt()) that a
 * flat substitution can't represent: without it, a DB override of one of
 * those prompts would have to be a single static string, silently losing
 * every branch. Deliberately NOT nestable (one level of `{{#if}}` only) —
 * every real prompt in this codebase needs at most that; a template
 * author who needs more should split into multiple prompt keys instead of
 * this growing into a template language.
 */
final class PromptTemplateRenderer
{
    /**
     * @param  array<string, string>  $variables
     *
     * @throws InvalidArgumentException if the template references a
     *                                   placeholder, or an `{{#if}}` condition variable, not present
     *                                   in $variables — silently treating a typo as "false"/empty is
     *                                   worse than failing the request outright.
     */
    public function render(string $template, array $variables): string
    {
        $template = $this->resolveConditionals($template, $variables);

        preg_match_all('/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/', $template, $matches);

        foreach (array_unique($matches[1]) as $placeholder) {
            if (! array_key_exists($placeholder, $variables)) {
                throw new InvalidArgumentException(sprintf(
                    'Prompt template references undefined placeholder "{{%s}}".',
                    $placeholder
                ));
            }
        }

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_]+)\s*\}\}/',
            fn (array $match) => $variables[$match[1]],
            $template
        );
    }

    /**
     * Resolves every (non-nested) `{{#if var}}...{{/if}}` /
     * `{{#if var}}...{{else}}...{{/if}}` block to whichever branch applies
     * — $var is truthy when it exists in $variables and is a non-empty
     * (after trim) string. Runs before the plain `{{var}}` pass in
     * render(), so a placeholder that only appears in the *dropped* branch
     * never has to exist in $variables.
     *
     * @param  array<string, string>  $variables
     */
    private function resolveConditionals(string $template, array $variables): string
    {
        return preg_replace_callback(
            '/\{\{#if\s+([a-zA-Z0-9_]+)\s*\}\}(.*?)(?:\{\{else\}\}(.*?))?\{\{\/if\}\}/s',
            function (array $match) use ($variables): string {
                $conditionVar = $match[1];

                if (! array_key_exists($conditionVar, $variables)) {
                    throw new InvalidArgumentException(sprintf(
                        'Prompt template references undefined {{#if %s}} condition variable.',
                        $conditionVar
                    ));
                }

                $isTruthy = trim($variables[$conditionVar]) !== '';
                $trueBranch = $match[2];
                $falseBranch = $match[3] ?? '';

                return $isTruthy ? $trueBranch : $falseBranch;
            },
            $template
        );
    }
}
