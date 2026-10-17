<x-layouts::app :title="__($title)">
    <div class="flex flex-col gap-6">
        <x-page-header :title="__($title)" :description="__('This part of WRJ HRIS is being built.')" />

        <x-data-card>
            <x-empty-state
                icon="wrench-screwdriver"
                :title="__('This page is coming soon.')"
                :description="__('It will appear here once this part of the system is ready.')"
            />
        </x-data-card>
    </div>
</x-layouts::app>
