# Browser Sub-agent Restrictions

> **CRITICAL**: Read this rule before using the `browser_subagent` tool.

---

## 🚫 Pre-approval is REQUIRED

You must **NEVER** launch the browser sub-agent (`browser_subagent`) without first obtaining explicit approval from the user.

### Reason
- The browser sub-agent consumes a significant number of tokens and can be very expensive.
- Keeping token usage optimized is a high priority for this project.

### Instructions for AI Agents
1. Before proposing or using `browser_subagent`, explain to the user exactly why you want to use the browser sub-agent and what actions it will take.
2. Explicitly ask the user for permission to proceed.
3. Wait for the user to respond with approval before invoking the tool.
