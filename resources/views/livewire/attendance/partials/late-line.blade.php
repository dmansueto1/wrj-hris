{{-- Phone cards (T-16.1): the amber "Late 0.42 h" badge under a day's attendance when late. $day: App\Support\AttendanceDay. --}}
@if (($late = $day->late()) !== null)
    <div>@include('livewire.attendance.partials.late-badge', ['hours' => $late, 'label' => true, 'test' => 'late-line'])</div>
@endif
