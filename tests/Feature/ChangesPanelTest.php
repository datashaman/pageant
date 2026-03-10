<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\WorkItem;
use App\Services\WorktreeBrowser;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->organization = Organization::factory()->create();
    $this->user->organizations()->attach($this->organization);
    $this->workItem = WorkItem::factory()->for($this->organization)->create([
        'source' => 'github',
        'source_reference' => 'org/repo#1',
    ]);
});

it('renders the panel component', function () {
    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->assertOk();
});

it('toggles the panel open and closed', function () {
    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->assertSet('panelOpen', false)
        ->call('togglePanel')
        ->assertSet('panelOpen', true)
        ->call('togglePanel')
        ->assertSet('panelOpen', false);
});

it('switches between changes and files tabs', function () {
    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->assertSet('activeTab', 'changes')
        ->call('setTab', 'files')
        ->assertSet('activeTab', 'files')
        ->call('setTab', 'changes')
        ->assertSet('activeTab', 'changes');
});

it('switches diff mode between local and base', function () {
    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->assertSet('diffMode', 'base')
        ->call('setDiffMode', 'local')
        ->assertSet('diffMode', 'local')
        ->call('setDiffMode', 'base')
        ->assertSet('diffMode', 'base');
});

it('shows no worktree message when work item has no worktree', function () {
    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->call('togglePanel')
        ->assertSee('No Worktree');
});

it('navigates directories in file tree', function () {
    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->assertSet('currentDirectory', '')
        ->call('navigateToDirectory', 'src')
        ->assertSet('currentDirectory', 'src')
        ->call('navigateToDirectory', 'src/Models')
        ->assertSet('currentDirectory', 'src/Models')
        ->call('navigateUp')
        ->assertSet('currentDirectory', 'src')
        ->call('navigateUp')
        ->assertSet('currentDirectory', '');
});

it('views and closes file contents', function () {
    $mock = Mockery::mock(WorktreeBrowser::class);
    $mock->shouldReceive('getFileContents')
        ->with(Mockery::type(WorkItem::class), 'test.php')
        ->andReturn('<?php echo "hello";');
    $mock->shouldReceive('hasWorktree')->andReturn(true);
    $mock->shouldReceive('getDiff')->andReturn([
        'diff' => '',
        'stats' => ['files_changed' => 0, 'insertions' => 0, 'deletions' => 0],
    ]);
    $mock->shouldReceive('getChangedFiles')->andReturn([]);
    $mock->shouldReceive('getFileTree')->andReturn([]);
    app()->instance(WorktreeBrowser::class, $mock);

    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->call('viewFile', 'test.php')
        ->assertSet('viewingFile', 'test.php')
        ->assertSet('fileContents', '<?php echo "hello";')
        ->call('closeFile')
        ->assertSet('viewingFile', null)
        ->assertSet('fileContents', null);
});

it('clears file view when switching tabs', function () {
    $mock = Mockery::mock(WorktreeBrowser::class);
    $mock->shouldReceive('getFileContents')
        ->andReturn('file content');
    $mock->shouldReceive('hasWorktree')->andReturn(true);
    $mock->shouldReceive('getDiff')->andReturn([
        'diff' => '',
        'stats' => ['files_changed' => 0, 'insertions' => 0, 'deletions' => 0],
    ]);
    $mock->shouldReceive('getChangedFiles')->andReturn([]);
    $mock->shouldReceive('getFileTree')->andReturn([]);
    app()->instance(WorktreeBrowser::class, $mock);

    Livewire::actingAs($this->user)
        ->test('changes-files-panel', ['workItem' => $this->workItem])
        ->call('viewFile', 'test.php')
        ->assertSet('viewingFile', 'test.php')
        ->call('setTab', 'changes')
        ->assertSet('viewingFile', null)
        ->assertSet('fileContents', null);
});
