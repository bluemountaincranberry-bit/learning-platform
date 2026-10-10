# Upstream

Source: https://github.com/browserbase/skills/tree/main/skills/ui-test

Installed with `npx --yes skills add browserbase/skills --skill ui-test --agent codex --copy -y`. The skill declares MIT in its frontmatter (version 0.4.0); the upstream repository has no root LICENSE file. It supports Codex skill format. Local browser use requires the Browserbase `browse` CLI; remote Browserbase use additionally needs credentials. On some systems `/usr/bin/browse` is an unrelated desktop URL opener, so verify `browse --help` before treating it as the Browserbase runtime. This environment's `/usr/bin/browse` is `xdg-open`. The skill requests delegated test groups and bounded step budgets.
