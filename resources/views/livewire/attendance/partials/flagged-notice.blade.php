{{--
    Amber notice for each flagged attendance (T-13): not clocked out and waiting for the Admin.
    It never blocks clocking in. Used on /clock and in step 2 of the kiosk.
--}}
@foreach ($flagged as $attendance)
    <flux:callout variant="warning" icon="exclamation-triangle" data-test="{{ $test }}">
        <flux:callout.text>
            {{ __('You have an attendance from :date that was not clocked out. Your admin will correct it.', ['date' => $attendance->work_date->format('M j, Y')]) }}
        </flux:callout.text>
    </flux:callout>
@endforeach
