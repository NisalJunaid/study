@props([
    'text',
    'pending' => false,
])

<div class="feedback-block">
    <p class="feedback-copy js-answer-pending-message" style="display: {{ $pending ? 'block' : 'none' }};">💬 We are finishing your grading. Feedback will appear soon.</p>
    <p class="feedback-copy js-answer-feedback-text" style="display: {{ $pending ? 'none' : 'block' }};">💬 {{ $text }}</p>
</div>
