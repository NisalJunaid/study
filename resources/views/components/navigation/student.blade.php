<nav class="nav-list">
    <a class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}">Dashboard</a>
    <a class="nav-item {{ request()->routeIs('student.levels.*') ? 'active' : '' }}" href="{{ route('student.levels.index') }}">Levels</a>
    <a class="nav-item {{ request()->routeIs('student.quiz.*') ? 'active' : '' }}" href="{{ route('student.quiz.setup') }}">Start Quiz</a>
    <a class="nav-item {{ request()->routeIs('student.history.*') ? 'active' : '' }}" href="{{ route('student.history.index') }}">History</a>
    <a class="nav-item {{ request()->routeIs('student.progress.*') ? 'active' : '' }}" href="{{ route('student.progress.index') }}">Progress</a>
</nav>
