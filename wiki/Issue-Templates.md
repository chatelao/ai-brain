# Issue Templates

Issue Templates allow you to quickly create standardized GitHub issues with dynamic parameters directly from the Agent Control interface.

## Creating a Template

1. Navigate to the **Templates** page from the top navigation bar.
2. Click **"Create New Template"**.
3. Fill in the template details:
    - **Name**: A descriptive name for the template.
    - **Title Template**: The format for the GitHub issue title. Use `%1`, `%2`, etc., as placeholders.
    - **Body Template**: The format for the GitHub issue description. You can also use placeholders here.
    - **Parameters Configuration**: Assign human-readable labels to your placeholders (e.g., `%1` -> "Feature Name").

## Using a Template

Once a template is created, it becomes available on the **Project Details** page.

1. Open a **Project**.
2. Locate the **"Create Issue from Template"** card.
3. Select your template from the dropdown.
4. Fill in the values for the defined parameters.
5. (Optional) Check **"Add 'Jules' label"** to automatically flag this issue for the AI agent.
6. Click **"Create Issue"**.

The application will generate the title and body by replacing placeholders with your provided values and then create the issue on GitHub.

---
Next: [Integrations](Integrations.md)
