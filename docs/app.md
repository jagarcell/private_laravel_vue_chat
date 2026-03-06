# MCP Application Rules

## 1) Keep Controllers Thin
Avoid fat controllers. Create Services and Repositories so business logic and database query logic stay out of controllers.

## 2) Use Form Requests for Validation
Always use Laravel `FormRequest` classes for request validation instead of inline controller validation.

## 3) Use API Resources for Responses
Use API Resource classes to build and standardize API response payloads.

## 4) Add Complete Doc Blocks
Add doc blocks to functions that include:
- Parameters (`@param`)
- Return value (`@return`)
- Logic workflow explanation (`Logic:` steps)
