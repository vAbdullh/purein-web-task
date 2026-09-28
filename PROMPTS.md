## 1. init the task enviroment and docker
(Antigravity Gemini 3.1 pro)

Dockerize current directory with simple docker file and dont run or build the project.

## 2. Analyze the project and identify the security issues,
(Claude Sonnet 4.6 Thinking)

Analyze the project files, explain each file’s briefly, entry points, and how requests flow through the app. Explain authentication, session handling, logout, and authorization and where those are implemented and the missing. List the database tables, columns, relationships. also identify security weaknesses in the code, use md table with: issue, evidence file with line, priority, and suggested fix.

Do not implement fixes yet, and dont modifying any file.

## 3. Fix security findings
(ChatGPT GPT-6 Astra)

Fix the security issues listed in @findings.md. Apply the changes directly to the project using multiple agents:
- Master PM to verify findings, divide tasks, assign file ownership, and coordinate dependencies.
- 3 developer agents, with more if needed, to implement fixes and report their changes and verification results. and make sure if they work in parallel they not intersect to each otehr without PM coordination 
- One QA agent to review the changes and provide clear, actionable feedback.

The PM review each fix before sending it to QA, validate QA  afeedback,nd assign confirmed issues back to developers. Repeat until all listed issues are resolved.

make sure to add short comments where helpful,update the README with the new changes.

Do not commit changes. The app is running on port 8080. Do not run commands or tests except the following rebuild and restart command, if needed:

```powershell
docker build -t purein-web-app .
docker stop purein-web-container
docker rm purein-web-container
docker run -d --name purein-web-container -p 8080:80 --env-file .env purein-web-app
```

## 4. Error handling and responses
(ChatGPT GPT-6.1 Astra)

Improve the application error handling and make the error messages more user-friendly:
- for 429 error show a clear message directly inside the login form instead of a separate error page.
- for 403, show 404 to protect critical files and directories
- For Access denied just show a clear message with a button that takes the user back to /sales.php showing the default station of user
- handle other expected errors properly with clear, human-readable messages and appropriate navigation options such as go back or try again.
- never expose stack traces, internal exception details, file paths, database errors, or other sensitive debugging information in HTTP responses or the UI. log technical details server-side when needed, while showing users only safe error messages.
- update the README to document the new error-handling behavior and security improvements.

## 5. localization and ar/en switch
(ChatGPT GPT-6 Astra)

what is the best approcch for php to do: ar/en rtl/ltr ? and to makes the phone vies sales responsivly?

--- 
Work with this plan and make sure to implement rtl properly (in html tag and query parameters), add swtich languages buttons.