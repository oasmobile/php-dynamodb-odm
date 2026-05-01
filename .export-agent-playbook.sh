#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
cd "$SCRIPT_DIR"

OUTPUT="agent-playbook.zip"

FILES=(
  # this script itself
  .export-agent-playbook.sh

  # root
  AGENTS.md
  PROJECT.md

  # Cursor rules
  .cursor/rules/cursor-scope.mdc
  .cursor/rules/doc/writing-conventions.mdc
  .cursor/rules/git/branch-overview.mdc
  .cursor/rules/git/git-conventions.mdc
  .cursor/rules/graphify.mdc
  .cursor/rules/safety/command-safety.mdc
  .cursor/rules/safety/config-safety.mdc
  .cursor/rules/spec/manual-testing.mdc
  .cursor/rules/spec/spec-execution.mdc
  .cursor/rules/spec/spec-goal.mdc
  .cursor/rules/spec/spec-planning.mdc
  .cursor/rules/plugin/superpowers-integration.mdc

  # Cursor skills
  .cursor/skills/graphify/SKILL.md
  .cursor/skills/graphify/references/incremental-and-modes.md
  .cursor/skills/graphify/references/integrations.md
  .cursor/skills/graphify/references/pipeline-steps.md
  .cursor/skills/graphify/references/query-commands.md

  # Cursor sub-agents
  .cursor/agents/code-reviewer.md
  .cursor/agents/doc-operator.md
  .cursor/agents/gitflow-finisher.md
  .cursor/agents/gitflow-starter.md
  .cursor/agents/spec-planner.md

  # Kiro sub-agents
  .kiro/agents/code-reviewer.md
  .kiro/agents/doc-operator.md
  .kiro/agents/gitflow-finisher.md
  .kiro/agents/gitflow-starter.md
  .kiro/agents/spec-gatekeeper.md

  # Kiro skills
  .kiro/skills/graphify/SKILL.md
  .kiro/skills/graphify/references/incremental-and-modes.md
  .kiro/skills/graphify/references/integrations.md
  .kiro/skills/graphify/references/pipeline-steps.md
  .kiro/skills/graphify/references/query-commands.md

  # Kiro steering
  .kiro/steering/graphify.md
  .kiro/steering/kiro-scope.md
  .kiro/steering/doc/writing-conventions.md
  .kiro/steering/git/branch-overview.md
  .kiro/steering/git/git-conventions.md
  .kiro/steering/safety/command-safety.md
  .kiro/steering/safety/config-safety.md
  .kiro/steering/spec/manual-testing.md
  .kiro/steering/spec/spec-execution.md
  .kiro/steering/spec/spec-planning.md
  .kiro/steering/spec/spec-goal.md
  .kiro/steering/spec/gk-requirements.md
  .kiro/steering/spec/gk-design.md
  .kiro/steering/spec/gk-tasks.md

  # doc layer READMEs
  docs/state/README.md
  docs/proposals/README.md
  docs/notes/README.md
  docs/changes/README.md
  docs/manual/README.md
  issues/README.md
)

rm -f "$OUTPUT"

# Temporarily swap PROJECT.md with template version
if [[ -f PROJECT.md ]]; then
  mv PROJECT.md PROJECT.md.bak
fi
cat > PROJECT.md << 'EOF'
# Project Name

<!-- 填写项目的技术栈、构建命令、运行入口、版本号位置、敏感文件清单 -->
<!-- 供 Agent 读取，详见 kiro-scope / cursor-scope 中的引用说明 -->
EOF

missing=()
for f in "${FILES[@]}"; do
  if [[ ! -f "$f" ]]; then
    missing+=("$f")
  fi
done

if [[ ${#missing[@]} -gt 0 ]]; then
  # Restore PROJECT.md before exiting
  rm -f PROJECT.md
  [[ -f PROJECT.md.bak ]] && mv PROJECT.md.bak PROJECT.md
  echo "ERROR: missing files:"
  printf "  %s\n" "${missing[@]}"
  exit 1
fi

zip -q "$OUTPUT" "${FILES[@]}"

# Restore PROJECT.md
rm -f PROJECT.md
[[ -f PROJECT.md.bak ]] && mv PROJECT.md.bak PROJECT.md

echo "Exported ${#FILES[@]} files → $OUTPUT"
