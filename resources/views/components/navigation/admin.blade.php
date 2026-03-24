<nav class="nav-list" style="margin-top:1.25rem">
    <a class="nav-item {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}" href="{{ route('admin.dashboard') }}">Dashboard</a>
    <a class="nav-item {{ request()->routeIs('admin.subjects.*') ? 'active' : '' }}" href="{{ route('admin.subjects.index') }}">Subjects</a>
    <a class="nav-item {{ request()->routeIs('admin.topics.*') ? 'active' : '' }}" href="{{ route('admin.topics.index') }}">Topics</a>
    <a class="nav-item {{ request()->routeIs('admin.questions.*') ? 'active' : '' }}" href="{{ route('admin.questions.index') }}">Questions</a>
    <a class="nav-item {{ request()->routeIs('admin.imports.*') ? 'active' : '' }}" href="{{ route('admin.imports.index') }}">Imports</a>
    <a class="nav-item {{ request()->routeIs('admin.theory-reviews.*') ? 'active' : '' }}" href="{{ route('admin.theory-reviews.index') }}">Theory Reviews</a>
</nav>
