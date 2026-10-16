<?php

namespace App\Livewire\Concerns;

use App\Support\Pagination;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Pagination with a "Rows per page" choice (10 by default), kept in the URL as ?per_page=25.
 * Use it in every Livewire component that shows a table, and render <x-table-footer> under the table.
 */
trait WithPerPage
{
    use WithPagination;

    #[Url(as: 'per_page', except: Pagination::DEFAULT_PER_PAGE)]
    public int $perPage = Pagination::DEFAULT_PER_PAGE;

    public function updatedPerPage(): void
    {
        $this->perPage = Pagination::perPage($this->perPage);
        $this->resetPage();
    }

    /**
     * The page size to pass to ->paginate(), always one of the allowed options.
     */
    protected function perPage(): int
    {
        return Pagination::perPage($this->perPage);
    }
}
