<nav class="nav-list" data-student-nav>
    <a class="nav-item {{ request()->routeIs('student.dashboard') || request()->routeIs('student.levels.*') || request()->routeIs('student.quiz.setup') ? 'active' : '' }}" href="{{ route('student.quiz.setup') }}">Build Quiz</a>
    <a class="nav-item {{ request()->routeIs('student.history.*') ? 'active' : '' }}" href="{{ route('student.history.index') }}">History</a>
    <a class="nav-item {{ request()->routeIs('student.progress.*') ? 'active' : '' }}" href="{{ route('student.progress.index') }}">Progress</a>
    <a class="nav-item {{ request()->routeIs('student.results.*') ? 'active' : '' }}" href="{{ route('student.results.index') }}">Results</a>
</nav>
