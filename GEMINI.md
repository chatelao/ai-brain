# Goal

Create a PHP application to control agents from Google Jules and coordinated by GitHub repository andissues 
running on a php webserver using a MySQL database and Google SSO login for multi user management.

# Structure

- `CONCEPT.md`: The overall structure of the product, including Business Cases & Use Cases as well as the overall High-Level Architecture, etc.
- `DESIGN.md`: The detailed design of the solution, including the architecture, used tech stack for development, production and testing, etc.
- `ROADMAP.md`: The list of accomplished and planned steps of the project, it should be group into Phases, Tasks and Subtasks if necessary. Checkboxes show the progress to be updated with every increment.
- `/specification/`: External Know-How as datasheet, standards, etc. Should be converted to Markdown if PDF, etc.
- `/src/`: The source code of the projec
- `/src/openapi.yaml`the REST-API definition used to generate the front- & backend glue-code
- `/src/frontend/`: The source code for the frontend (Browser) parts of the project
- `/src/backend/`: The source code for the PHP backend
- `/src/client-api/`: The source code for client api modules of the project
- `/src/sql/`: The SQL scripts for the schematic and patches between releases if necessary
- `/src/clients/`: Definition of external interfaces
- `/test/`: All tools, configurations & test cases
- `/build/`: Only temporary place for compilation, may be cached by Github
- `/scripts/install.sh` to install all tools to build the application (test only tools, see below)

# Testing Locally & with Github Action Workflow

- Write CI/CD test independent as `test` script of the Github action workflows
- Use `test/install.sh` to install test tools.
- Use the Github action workflows to run the tests after commits.
- Before committing fetch all changes from the remote repository and merge the changes
- Run the CI/CD on every commit on every branch
- Add as much caching as possible to the Github action workflows


