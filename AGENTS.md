# AGENTS.md

## Project
O'Level Study Help App

## Goal
Build a modern study-help web app for O'Level students using:
- Laravel 10
- React
- Inertia.js
- Tailwind CSS
- Laravel queues
- Laravel broadcasting + WebSockets
- OpenAI API for grading theory answers
- CSV bulk import for questions

The app must allow students to:
- select one or more subjects
- optionally select topics
- choose MCQ, theory, or mixed quiz mode
- answer quizzes
- receive results and feedback
- see quiz history and weak areas

The app must allow admins to:
- add/edit/delete subjects
- add/edit/delete topics
- add/edit/delete questions and answers
- manage both MCQ and theory questions
- bulk import questions via CSV
- review AI grading
- manually override theory grading
- see live progress updates without page refresh

---

## Product Principles
1. Use a modern monolith approach.
   - Laravel handles routing, auth, validation, jobs, broadcasting, policies, and persistence.
   - React + Inertia provides SPA-like UX without building a separate API frontend.

2. Keep admin and student experiences separate but within the same app.

3. Use real-time updates wherever users would otherwise need to refresh:
   - CSV import progress
   - theory grading completion
   - admin list updates
   - quiz autosave status
   - live dashboard activity

4. Treat AI grading as assistive, not final truth.
   - Always store raw AI grading JSON.
   - Allow admin override.
   - Support manual review.

5. Prefer reliable and explicit data modeling over “smart” shortcuts.

---

## High-Level Architecture

### App Shape
This is a single Laravel application with:
- server-side routes
- Inertia responses
- React pages/components
- queue workers for background work
- WebSocket broadcasting for live UX
- OpenAI integration for theory grading

### Main Domains
- Authentication / authorization
- Subjects and topics
- Question bank
- Quiz generation and attempts
- Student answers and grading
- Imports
- Realtime events
- Admin analytics

---

## Tech Stack

### Backend
- Laravel 10
- PHP 8.2+
- MySQL or MariaDB
- Redis for queue + cache + broadcasting support
- Laravel Reverb for WebSockets
- Laravel Echo on frontend
- Jobs + events + listeners
- Policies / Gates
- Form Request validation
- Storage for uploaded files/images
- Optional: Laravel Excel or native CSV parser

### Frontend
- React
- Inertia.js
- Tailwind CSS
- Headless UI or Radix-style component patterns if needed
- Lightweight chart library for performance/progress views

### AI
- OpenAI Responses API
- Structured JSON output schema for theory grading

---

## User Roles

### student
Can:
- browse subjects/topics
- start quizzes
- submit answers
- view results
- see history / progress

Cannot:
- access admin routes
- manage questions/imports/users

### admin
Can:
- manage all curriculum entities
- manage question bank
- import questions
- review and override theory grading
- monitor attempts

Optional future role:
- examiner / moderator
  - can review grading but not full system config

---

## Core Features

### Student Features
1. Subject selection
2. Optional topic filtering
3. Quiz mode:
   - MCQ
   - Theory
   - Mixed
4. Quiz size selection
5. Timed or untimed mode
6. Answer autosave
7. Quiz submission
8. MCQ immediate correctness after submit
9. Theory grading via AI job
10. Results with topic performance breakdown
11. Progress/history dashboard

### Admin Features
1. Subject CRUD
2. Topic CRUD
3. Question CRUD
4. MCQ option CRUD
5. Theory answer reference/rubric CRUD
6. Publish/unpublish questions
7. CSV upload + validation preview + import
8. Import log and failure review
9. Theory answer review queue
10. Manual override of AI grading
11. Live system dashboard

---

## UI / UX Direction

## Theme Name
Focus Lab

## Visual Language
- clean academic interface with modern polish
- rounded cards
- soft gradients
- subtle shadows
- strong spacing
- color-coded subjects
- calm but lively motion
- light and dark mode capable

## Student UX
- welcoming dashboard
- card-based subject browsing
- wizard-based quiz builder
- one-question-at-a-time quiz mode
- clear progress indicators
- confidence-building feedback, not punitive wording

## Admin UX
- productivity-first
- split layout
- sticky filters + actions
- drawers/modals for create/edit
- fast keyboard-friendly forms
- bulk actions
- live status badges

---

## Information Architecture

### Student Routes
- /dashboard
- /subjects
- /subjects/{subject}
- /quiz/create
- /quiz/{quiz}
- /quiz/{quiz}/results
- /history
- /progress

### Admin Routes
- /admin
- /admin/subjects
- /admin/topics
- /admin/questions
- /admin/questions/create
- /admin/questions/{question}/edit
- /admin/imports
- /admin/imports/{import}
- /admin/attempts
- /admin/theory-reviews

---

## Suggested Folder Structure

### Laravel
- app/
  - Actions/
  - Events/
  - Http/
    - Controllers/
      - Admin/
      - Student/
    - Requests/
      - Admin/
      - Student/
    - Resources/
  - Jobs/
  - Models/
  - Policies/
  - Services/
    - Quiz/
    - Import/
    - AI/
    - Analytics/
  - Support/
    - CSV/
    - Grading/
    - DTOs/
- database/
  - migrations/
  - seeders/
  - factories/
- resources/
  - js/
    - Components/
    - Layouts/
    - Pages/
      - Admin/
      - Student/
    - Hooks/
    - Utils/
    - Types/
- routes/
  - web.php
  - auth.php
  - channels.php

### React Page Structure
- Pages/Admin/Dashboard.jsx
- Pages/Admin/Subjects/Index.jsx
- Pages/Admin/Topics/Index.jsx
- Pages/Admin/Questions/Index.jsx
- Pages/Admin/Questions/Form.jsx
- Pages/Admin/Imports/Index.jsx
- Pages/Admin/Imports/Show.jsx
- Pages/Admin/TheoryReviews/Index.jsx

- Pages/Student/Dashboard.jsx
- Pages/Student/Subjects/Index.jsx
- Pages/Student/Subjects/Show.jsx
- Pages/Student/Quiz/Builder.jsx
- Pages/Student/Quiz/Take.jsx
- Pages/Student/Quiz/Results.jsx
- Pages/Student/History/Index.jsx
- Pages/Student/Progress/Index.jsx

---

## Database Blueprint

### users
- id
- name
- email
- password
- role enum('admin','student')
- email_verified_at
- remember_token
- timestamps

### subjects
- id
- name
- slug
- description nullable
- color nullable
- icon nullable
- is_active boolean default true
- sort_order integer default 0
- timestamps

### topics
- id
- subject_id fk
- name
- slug
- description nullable
- is_active boolean default true
- sort_order integer default 0
- timestamps

### questions
- id
- subject_id fk
- topic_id fk nullable
- type enum('mcq','theory')
- question_text longText
- question_image_path nullable
- difficulty enum('easy','medium','hard') nullable
- explanation longText nullable
- marks decimal(5,2) default 1
- is_published boolean default false
- created_by fk users.id nullable
- updated_by fk users.id nullable
- timestamps
- softDeletes

### mcq_options
- id
- question_id fk
- option_key string(5)   // A, B, C, D
- option_text longText
- is_correct boolean default false
- sort_order integer default 0
- timestamps

### theory_question_meta
- id
- question_id fk unique
- sample_answer longText
- grading_notes longText nullable
- keywords json nullable
- acceptable_phrases json nullable
- max_score decimal(5,2) default 1
- timestamps

### quizzes
- id
- user_id fk
- subject_id fk nullable
- mode enum('mcq','theory','mixed')
- status enum('draft','in_progress','submitted','grading','graded')
- total_questions integer default 0
- total_possible_score decimal(8,2) default 0
- total_awarded_score decimal(8,2) nullable
- started_at timestamp nullable
- submitted_at timestamp nullable
- graded_at timestamp nullable
- timestamps

### quiz_questions
- id
- quiz_id fk
- question_id fk
- order_no integer
- question_snapshot json
- max_score decimal(5,2) default 1
- awarded_score decimal(5,2) nullable
- is_correct boolean nullable
- requires_manual_review boolean default false
- timestamps

### student_answers
- id
- quiz_question_id fk
- question_id fk
- user_id fk
- selected_option_id fk nullable
- answer_text longText nullable
- is_correct boolean nullable
- score decimal(5,2) nullable
- feedback longText nullable
- grading_status enum('pending','processing','graded','manual_review','overridden') default 'pending'
- ai_result_json json nullable
- graded_by fk users.id nullable
- graded_at timestamp nullable
- timestamps

### imports
- id
- uploaded_by fk users.id
- file_name string
- file_path string
- status enum('uploaded','validating','ready','importing','completed','failed','partially_completed')
- total_rows integer default 0
- valid_rows integer default 0
- imported_rows integer default 0
- failed_rows integer default 0
- error_summary longText nullable
- completed_at timestamp nullable
- timestamps

### import_rows
- id
- import_id fk
- row_number integer
- raw_payload json
- validation_errors json nullable
- status enum('pending','valid','invalid','imported','failed') default 'pending'
- related_question_id fk nullable
- timestamps

### audit_logs (optional but recommended)
- id
- user_id fk nullable
- action string
- auditable_type string
- auditable_id bigint nullable
- old_values json nullable
- new_values json nullable
- timestamps

---

## Eloquent Relationships

### User
- hasMany quizzes
- hasMany imports
- hasMany createdQuestions via questions.created_by
- hasMany updatedQuestions via questions.updated_by

### Subject
- hasMany topics
- hasMany questions

### Topic
- belongsTo subject
- hasMany questions

### Question
- belongsTo subject
- belongsTo topic nullable
- hasMany mcqOptions
- hasOne theoryMeta
- hasMany quizQuestions

### Quiz
- belongsTo user
- belongsTo subject nullable
- hasMany quizQuestions

### QuizQuestion
- belongsTo quiz
- belongsTo question
- hasOne studentAnswer

### StudentAnswer
- belongsTo quizQuestion
- belongsTo question
- belongsTo user
- belongsTo selectedOption nullable

### Import
- belongsTo uploadedBy user
- hasMany importRows

---

## Question Types

### MCQ
Required:
- question_text
- at least 2 options
- exactly 1 correct option
- subject_id

Optional:
- topic_id
- explanation
- image
- difficulty
- marks

### Theory
Required:
- question_text
- sample_answer
- subject_id

Optional:
- topic_id
- grading_notes
- keywords
- acceptable_phrases
- image
- difficulty
- marks

---

## CSV Import Format

Use a single CSV schema that supports both MCQ and theory questions.

### Required Columns
- subject
- topic
- type
- question_text
- difficulty
- marks
- is_published

### MCQ Columns
- option_a
- option_b
- option_c
- option_d
- option_e
- correct_option
- explanation

### Theory Columns
- sample_answer
- grading_notes
- keywords
- acceptable_phrases

### Example CSV
subject,topic,type,question_text,difficulty,marks,is_published,option_a,option_b,option_c,option_d,option_e,correct_option,explanation,sample_answer,grading_notes,keywords,acceptable_phrases
Mathematics,Algebra,mcq,"What is 2x when x = 3?",easy,1,1,"3","5","6","8","","C","2 multiplied by 3 equals 6","","","",""
English,Essay Writing,theory,"Explain why punctuation is important in writing.",medium,3,1,"","","","","","","","Punctuation helps clarify meaning, structure sentences, and guide pauses.","Expect meaning, clarity, and sentence structure.","clarity|meaning|structure","guides pauses|separates ideas"

### CSV Rules
- subject is matched by name; create if missing only if admin enables that mode
- topic is matched within subject
- type must be mcq or theory
- correct_option must match existing option key
- keywords and acceptable_phrases may be pipe-separated and converted to arrays
- blank topic is allowed
- blank explanation is allowed
- blank theory fields are not allowed for theory rows
- MCQ must have one correct option and non-empty question text
- import must support preview before commit

---

## Import Workflow

1. Admin uploads CSV
2. App stores original file
3. Validation job parses rows
4. Preview page shows:
   - total rows
   - valid rows
   - invalid rows
   - row-by-row errors
5. Admin confirms import
6. Import job processes in chunks
7. Broadcast progress to frontend
8. Persist import summary and failures

### Import Broadcasting Events
- ImportValidationStarted
- ImportValidationCompleted
- ImportStarted
- ImportProgressUpdated
- ImportCompleted
- ImportFailed

### Import Rules
- imports must be idempotent where practical
- failed rows should not fail entire import
- persist raw row payload for debugging
- allow downloading failure report CSV in future iteration

---

## Quiz Generation Rules

### Quiz Builder Inputs
- subject_id or multiple subject_ids
- optional topic_ids
- mode: mcq | theory | mixed
- question_count
- difficulty optional
- randomize true/false

### Selection Rules
- only use published questions
- filter by selected subject(s)
- filter by topics if given
- respect mode
- avoid duplicate question ids
- mixed mode should balance MCQ/theory if possible
- if insufficient questions exist, return a friendly validation error

### Snapshot Rule
Store a question snapshot in quiz_questions.question_snapshot at assignment time so future edits do not alter historical quiz attempts.

Snapshot should include:
- question text
- type
- explanation
- options for MCQ
- sample answer metadata only for grading pipeline if needed
- marks

Do not expose theory sample answer to student-facing UI.

---

## Quiz Taking Flow

1. Student opens quiz builder
2. Student selects subject, optional topics, mode, count
3. System creates quiz record with status=draft
4. System assigns quiz_questions
5. Quiz starts and status=in_progress
6. Student answers one question at a time
7. Answers autosave
8. Student submits quiz
9. MCQ answers grade immediately
10. Theory answers dispatch grading jobs
11. Quiz status becomes grading if theory exists
12. When all grading jobs finish, quiz status becomes graded
13. Student sees results, with live updates as grading completes

---

## Grading Rules

### MCQ Grading
- compare selected_option_id with correct option
- set is_correct true/false
- score = full marks if correct else 0
- explanation shown after submission

### Theory Grading
AI receives:
- question
- student answer
- sample correct answer
- grading notes
- keywords
- max_score

AI returns strict JSON:
- verdict
- score
- confidence
- matched_points
- missing_points
- feedback
- should_flag_for_review

### Theory Grading Principles
- answer evaluation should be conservative
- partial correctness is allowed
- score must not exceed max_score
- empty/irrelevant answers should score 0
- confidence below threshold should trigger manual review
- unsafe or malformed AI output should fail closed and mark manual_review

### Manual Review
Admin can:
- see original question
- see student answer
- see sample answer
- see AI result
- override score
- edit feedback
- mark review complete

Store:
- overridden score
- reviewer id
- review timestamp

---

## OpenAI Integration Blueprint

### Service
Create a dedicated service class:
- App\Services\AI\TheoryGraderService

### Responsibilities
- build prompt
- define JSON schema
- call OpenAI Responses API
- validate response shape
- normalize data
- return safe DTO/result object

### Required Output Schema
{
  "verdict": "correct | partially_correct | incorrect",
  "score": 0,
  "confidence": 0,
  "matched_points": [],
  "missing_points": [],
  "feedback": "",
  "should_flag_for_review": false
}

### Prompting Rules
- never ask for free-form prose without schema
- explicitly instruct the model to compare against the sample answer and grading notes
- forbid revealing hidden grading notes to student
- keep feedback concise and student-friendly
- require numeric score bounded by max_score

### Failure Handling
If API fails or schema invalid:
- mark grading_status=manual_review
- store error metadata
- notify admin queue/dashboard
- do not silently pass

---

## Realtime / Sockets Blueprint

Use broadcasting for:
- import validation progress
- import processing progress
- theory answer graded events
- quiz status updates
- admin question list refresh signals
- live admin dashboard counts

### Channels
- private-user.{userId}
- private-quiz.{quizId}
- private-import.{importId}
- private-admin.dashboard
- private-admin.questions

### Frontend Realtime Patterns
- subscribe in React hooks
- update local state optimistically
- handle reconnect gracefully
- no hard dependence on sockets for correctness
- always persist truth in DB and rehydrate via normal request if needed

### Events
- TheoryAnswerGraded
- QuizGradingProgressUpdated
- ImportProgressUpdated
- QuestionBankChanged
- AdminDashboardMetricsUpdated

---

## Authorization Rules

Use policies and middleware.

### Admin-only
- all /admin routes
- imports
- question CRUD
- grading review
- publish/unpublish

### Student-only
- own quizzes
- own history
- own results

### Sensitive Rules
- students can only access their own quiz attempts
- admin override actions must be audited
- unpublished questions must never appear in student quiz generation

---

## Validation Rules

### Subject
- unique name
- slug unique

### Topic
- unique per subject
- slug unique within subject or globally, depending on implementation

### Question
- subject required
- type required
- question_text required
- marks >= 0

### MCQ
- minimum 2 options
- exactly 1 correct option
- option text cannot be duplicated within same question

### Theory
- sample_answer required
- max_score > 0

### Quiz Builder
- subject(s) required
- mode required
- question_count positive integer
- requested question count must not exceed available published inventory

### CSV Import
- header validation required
- row-level validation persisted
- invalid rows reported clearly

---

## API / Controller Design Pattern

Use thin controllers, Form Requests, and service classes.

### Controllers should:
- authorize
- validate request
- call service/action
- return Inertia response or redirect

### Services/Actions should:
- encapsulate business logic
- be reusable from jobs/controllers/tests

Recommended actions/services:
- CreateQuestionAction
- UpdateQuestionAction
- DeleteQuestionAction
- BuildQuizAction
- GradeMcqQuizAction
- QueueTheoryGradingAction
- ProcessQuestionImportAction
- ValidateQuestionImportAction
- OverrideTheoryGradeAction

---

## Performance Rules

1. Eager load relationships in admin tables and quiz views.
2. Paginate admin lists.
3. Use DB indexes on:
   - subject_id
   - topic_id
   - type
   - is_published
   - quiz_id
   - user_id
   - grading_status
   - status
4. Queue expensive jobs:
   - theory grading
   - CSV validation/import
   - analytics refresh
5. Cache lightweight dashboard aggregates if needed.
6. Use snapshots to avoid expensive historical joins.

---

## Security Rules

1. Sanitize and validate all CSV input.
2. Restrict file uploads by mime and size.
3. Escape/render rich text safely.
4. Prevent students from seeing hidden grading metadata.
5. Rate limit quiz submission and AI-triggering routes if needed.
6. Protect channels with proper authorization callbacks.
7. Log admin override and destructive actions.
8. Use CSRF/session auth for web app.
9. Never trust client-sent score values.

---

## Testing Expectations

### Feature Tests
- student can build quiz
- unpublished questions excluded
- MCQ grading works
- theory answer submission queues job
- admin can CRUD questions
- admin can import CSV
- invalid rows are reported
- policies block unauthorized access

### Unit Tests
- quiz builder selection logic
- MCQ grading service
- theory grading normalization
- CSV row parser
- import validator
- score aggregation

### Browser / E2E Tests
- student quiz flow
- admin question create/edit
- CSV import preview
- live grading result update

### Realtime Tests
- broadcasting event payload format
- authorized channel access
- frontend gracefully handles event arrival

---

## Seed Data

Seed:
- demo admin
- demo student
- 5-8 subjects
- 5-20 topics
- sample MCQ questions
- sample theory questions
- sample quiz attempts

This helps agents build UI against realistic data.

---

## Suggested Milestones

### Phase 1
- auth + roles
- subject/topic CRUD
- question bank CRUD
- MCQ quiz flow
- student results page

### Phase 2
- theory questions
- OpenAI grading jobs
- review queue
- score aggregation
- quiz history

### Phase 3
- CSV preview + import
- import logs
- realtime progress
- admin dashboard metrics

### Phase 4
- analytics polish
- weak-topic recommendations
- dark mode
- notifications
- audit log UI

---

## Coding Conventions for Agents

1. Do not put heavy business logic in controllers.
2. Use Form Requests for validation.
3. Use policies for authorization.
4. Prefer service/action classes for reusable domain logic.
5. Keep React pages thin; move reusable UI into components.
6. Use TypeScript-style prop discipline even if not using TS.
7. Use optimistic UI only when server state is still authoritative.
8. Every async process must have:
   - persisted status
   - failure state
   - user-visible progress or message
9. Avoid hidden magic; prefer explicit naming.
10. Preserve historical quiz data with snapshots.

---

## UI Component Wishlist

### Shared
- AppShell
- PageHeader
- EmptyState
- ConfirmDialog
- DataTable
- FilterBar
- StatusBadge
- MetricCard
- ProgressRing
- ToastStack

### Student
- SubjectCard
- TopicChip
- QuizBuilderPanel
- QuestionCard
- McqOptionList
- TheoryAnswerBox
- QuizProgressBar
- QuizNavigator
- ResultSummaryCard
- TopicPerformanceChart

### Admin
- QuestionEditorDrawer
- McqOptionEditor
- TheoryRubricEditor
- ImportDropzone
- ImportValidationTable
- LiveImportProgressPanel
- ReviewQueuePanel
- AuditTrailPanel

---

## Non-Goals for Initial Version
- parent accounts
- payment/subscription
- multiplayer/live quizzes
- full curriculum recommendation engine
- multilingual UI
- offline-first PWA
- OCR document ingestion
- voice answer grading

---

## Definition of Done
A feature is only done when:
- backend validation exists
- authorization exists
- tests exist
- loading/error states exist
- empty states exist
- realtime does not replace durable persistence
- admin actions are auditable where relevant
- student-visible scoring is correct and explainable

---

## Notes for Agents
- Prioritize correctness of domain logic over visual flourishes.
- Keep the UI modern and polished, but do not sacrifice maintainability.
- Use queues and broadcasts for any action that may take noticeable time.
- Build the system so theory grading failures degrade safely to manual review.
- Preserve a clean separation between question authoring, quiz generation, and grading.