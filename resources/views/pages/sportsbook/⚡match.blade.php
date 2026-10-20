<?php

use App\Models\Fixture;
use App\Models\Market;
use App\Sportsbook\Catalogue\MarketTabs;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Layout('layouts::sportsbook')] class extends Component {
    public Fixture $fixture;

    #[Url(except: 'main')]
    public string $tab = 'main';

    public function mount(Fixture $fixture): void
    {
        abort_unless($fixture->competition->is_synced, 404);

        $this->fixture = $fixture;
        $this->updated();
    }

    public function updated(): void
    {
        $this->tab = array_key_exists($this->tab, MarketTabs::TABS) ? $this->tab : 'main';
    }

    /**
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, Market>> Markets per tab, empty tabs left out.
     */
    #[Computed]
    public function marketsByTab(): Collection
    {
        return MarketTabs::group($this->fixture->markets()->with('selections')->get());
    }

    public function render(): View
    {
        return $this->view()->title($this->fixture->name());
    }
};
?>

<x-sportsbook.match-details :$fixture :markets-by-tab="$this->marketsByTab" :$tab />
