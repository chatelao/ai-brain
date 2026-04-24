# HOWTO: Setup Read the Docs

This guide explains how to set up and build the documentation for AI Brain using Sphinx and Read the Docs.

## Local Setup

To build the documentation locally, you need Python installed. Follow these steps:

1. **Install dependencies:**
   ```bash
   pip install -r docs/requirements.txt
   ```

2. **Build HTML documentation:**
   ```bash
   sphinx-build -b html docs docs/_build/html
   ```

3. **View the documentation:**
   Open `docs/_build/html/index.html` in your web browser.

## Read the Docs Configuration

The project is pre-configured for Read the Docs (RTD) using the following files:

- `.readthedocs.yaml`: Defines the build environment (Ubuntu 22.04, Python 3.11) and points to the requirements file.
- `docs/requirements.txt`: Lists necessary Python packages (`sphinx`, `sphinx-rtd-theme`, `myst-parser`).
- `docs/conf.py`: Sphinx configuration file, including `myst_parser` setup for Markdown support.

## Embedding Markdown

We use the `myst_parser` to embed Markdown files into reStructuredText. This allows us to keep our main documentation in Markdown while using Sphinx's powerful features.

To include a Markdown file in a `.rst` file, use the following syntax:

```rst
.. include:: ../FILENAME.md
   :parser: myst_parser.sphinx_
```

## GitHub Actions Integration

The documentation is automatically built during the CI/CD process. You can find the build steps in `.github/workflows/ci.yml`. The generated HTML is uploaded as a workflow artifact named `all-artifacts`.
