<nav class="nav-list">
    <a class="nav-item {{ request()->routeIs('student.dashboard') ? 'active' : '' }}" href="{{ route('student.dashboard') }}">Dashboard</a>
    <a class="nav-item {{ request()->routeIs('student.subjects.*') ? 'active' : '' }}" href="{{ route('student.subjects.index') }}">Subjects</a>
    <a class="nav-item {{ request()->routeIs('student.quiz.*') ? 'active' : '' }}" href="{{ route('student.quiz.builder') }}">Quiz Builder</a>
    <a class="nav-item {{ request()->routeIs('student.history.*') ? 'active' : '' }}" href="{{ route('student.history.index') }}">History</a>
    <a class="nav-item {{ request()->routeIs('student.progress.*') ? 'active' : '' }}" href="{{ route('student.progress.index') }}">Progress</a>
</nav>
