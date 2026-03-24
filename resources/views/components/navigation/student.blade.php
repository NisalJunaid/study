<nav class="nav-list" data-student-nav>
    <a class="nav-item {{ request()->routeIs('student.dashboard') || request()->routeIs('student.levels.*') ? 'active' : '' }}" href="{{ route('student.levels.index') }}">Dashboard</a>
    <a class="nav-item {{ request()->routeIs('student.history.*') ? 'active' : '' }}" href="{{ route('student.history.index') }}">History</a>
    <a class="nav-item {{ request()->routeIs('student.progress.*') ? 'active' : '' }}" href="{{ route('student.progress.index') }}">Progress</a>
    <a class="nav-item {{ request()->routeIs('student.results.*') ? 'active' : '' }}" href="{{ route('student.results.index') }}">Results</a>
    <a class="nav-item {{ request()->routeIs('profile.edit') ? 'active' : '' }}" href="{{ route('profile.edit') }}">Profile</a>

    <form method="POST" action="{{ route('logout') }}" style="margin-top:.4rem">
        @csrf
        <button type="submit" class="nav-item nav-button">Logout</button>
    </form>
</nav>
