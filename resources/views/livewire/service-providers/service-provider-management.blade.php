<?php

use Livewire\Component;
use Livewire\Attributes\Url;
use App\Services\Mock\MockDataService;

new class extends Component
{
    #[Url(as: 'tab')]
    public string $tab = 'providers';

    public string $providerTypeFilter = '';
    public string $providerStatusFilter = '';
    public string $serviceProviderFilter = '';
    public string $serviceCategoryFilter = '';
    public string $serviceStatusFilter = '';

    public ?array $selectedProvider = null;
    public ?array $selectedService = null;

    public bool $showProviderCreateModal = false;
    public bool $showProviderEditModal = false;
    public bool $showServiceCreateModal = false;
    public bool $showServiceEditModal = false;

    public string $notification = '';

    public array $newProvider = [
        'name' => '',
        'code' => '',
        'type' => 'EXTERNAL_API',
        'baseUrl' => '',
        'credentialsRef' => '',
        'timeoutMillis' => 10000,
        'maxRetries' => 2,
        'retryBackoffMillis' => 500,
        'sandbox' => false,
        'supportsReferenceValidation' => false,
        'callbackSecretRef' => '',
    ];

    public array $editProvider = [];

    public array $newService = [
        'providerId' => '',
        'name' => '',
        'code' => '',
        'category' => 'ELECTRICITY',
        'minAmount' => null,
        'maxAmount' => null,
    ];

    public array $editService = [];

    public array $providerTypes = ['EXTERNAL_API', 'INTERNAL'];
    public array $providerStatuses = ['ACTIVE', 'INACTIVE'];
    public array $serviceCategories = ['ELECTRICITY', 'WATER', 'TV', 'TELECOM', 'AIRTIME', 'INTERNET', 'OTHER'];
    public array $serviceStatuses = ['ACTIVE', 'INACTIVE'];

    public function setTab(string $tab): void
    {
        $this->tab = $tab;
        $this->closeDrawer();
        $this->providerTypeFilter = '';
        $this->providerStatusFilter = '';
        $this->serviceCategoryFilter = '';
        $this->serviceStatusFilter = '';
    }

    public function selectProvider(string $id): void
    {
        $this->selectedProvider = MockDataService::serviceProvider($id);
        $this->selectedService = null;
    }

    public function selectService(string $id): void
    {
        $this->selectedService = MockDataService::billService('', $id);
        $this->selectedProvider = null;
    }

    public function closeDrawer(): void
    {
        $this->selectedProvider = null;
        $this->selectedService = null;
        $this->showProviderEditModal = false;
        $this->showServiceEditModal = false;
    }

    public function openProviderCreateModal(): void
    {
        $this->newProvider = $this->defaultProvider();
        $this->showProviderEditModal = false;
        $this->showProviderCreateModal = true;
    }

    public function createProvider(): void
    {
        $this->validate($this->providerRules('newProvider', true));

        // Real: POST /api/v1/backoffice/service-providers
        // Body: CreateServiceProviderRequest. Returns 202 ApprovalRequestResponse.
        $this->notification = 'Service provider change submitted for approval.';
        $this->showProviderCreateModal = false;
        $this->newProvider = $this->defaultProvider();
    }

    public function openProviderEditModal(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        $this->editProvider = [
            'id' => $this->selectedProvider['id'],
            'name' => $this->selectedProvider['name'],
            'baseUrl' => $this->selectedProvider['baseUrl'] ?? '',
            'credentialsRef' => '',
            'timeoutMillis' => $this->selectedProvider['timeoutMillis'],
            'maxRetries' => $this->selectedProvider['maxRetries'],
            'retryBackoffMillis' => $this->selectedProvider['retryBackoffMillis'],
            'sandbox' => $this->selectedProvider['sandbox'],
            'supportsReferenceValidation' => $this->selectedProvider['supportsReferenceValidation'],
            'callbackSecretRef' => '',
        ];
        $this->showProviderCreateModal = false;
        $this->showProviderEditModal = true;
    }

    public function updateProvider(): void
    {
        $this->validate($this->providerRules('editProvider', false));

        // Real: PUT /api/v1/backoffice/service-providers/{id}
        // Body: UpdateServiceProviderRequest. Returns 202 ApprovalRequestResponse.
        $this->notification = 'Service provider update submitted for approval.';
        $this->showProviderEditModal = false;
        $this->closeDrawer();
    }

    public function activateProvider(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        // Real: POST /api/v1/backoffice/service-providers/{id}/activate
        // Returns 202 ApprovalRequestResponse.
        $this->notification = 'Service provider activation submitted for approval.';
        $this->closeDrawer();
    }

    public function deactivateProvider(): void
    {
        if (!$this->selectedProvider) {
            return;
        }

        // Real: POST /api/v1/backoffice/service-providers/{id}/deactivate
        // Returns 202 ApprovalRequestResponse.
        $this->notification = 'Service provider deactivation submitted for approval.';
        $this->closeDrawer();
    }

    public function openServiceCreateModal(?string $providerId = null): void
    {
        $fallbackProvider = MockDataService::serviceProviders()[0]['id'] ?? '';
        $this->newService = [
            'providerId' => $providerId ?: ($this->serviceProviderFilter ?: $fallbackProvider),
            'name' => '',
            'code' => '',
            'category' => 'ELECTRICITY',
            'minAmount' => null,
            'maxAmount' => null,
        ];
        $this->showServiceEditModal = false;
        $this->showServiceCreateModal = true;
    }

    public function createService(): void
    {
        $this->validate($this->serviceRules('newService', true));

        // Real: POST /api/v1/backoffice/service-providers/{providerId}/services
        // Body: CreateBillServiceRequest includes providerId even though it is also in the path.
        // Returns 202 ApprovalRequestResponse.
        $this->notification = 'Bill service change submitted for approval.';
        $this->showServiceCreateModal = false;
        $this->newService = [
            'providerId' => '',
            'name' => '',
            'code' => '',
            'category' => 'ELECTRICITY',
            'minAmount' => null,
            'maxAmount' => null,
        ];
    }

    public function openServiceEditModal(): void
    {
        if (!$this->selectedService) {
            return;
        }

        $this->editService = [
            'id' => $this->selectedService['id'],
            'providerId' => $this->selectedService['providerId'],
            'name' => $this->selectedService['name'],
            'category' => $this->selectedService['category'],
            'minAmount' => $this->selectedService['minAmount'],
            'maxAmount' => $this->selectedService['maxAmount'],
        ];
        $this->showServiceCreateModal = false;
        $this->showServiceEditModal = true;
    }

    public function updateService(): void
    {
        $this->validate($this->serviceRules('editService', false));

        // Real: PUT /api/v1/backoffice/service-providers/{providerId}/services/{serviceId}
        // Body: UpdateBillServiceRequest. Returns 202 ApprovalRequestResponse.
        $this->notification = 'Bill service update submitted for approval.';
        $this->showServiceEditModal = false;
        $this->closeDrawer();
    }

    public function activateService(): void
    {
        if (!$this->selectedService) {
            return;
        }

        // Real: POST /api/v1/backoffice/service-providers/{providerId}/services/{serviceId}/activate
        // Returns 202 ApprovalRequestResponse.
        $this->notification = 'Bill service activation submitted for approval.';
        $this->closeDrawer();
    }

    public function deactivateService(): void
    {
        if (!$this->selectedService) {
            return;
        }

        // Real: POST /api/v1/backoffice/service-providers/{providerId}/services/{serviceId}/deactivate
        // Returns 202 ApprovalRequestResponse.
        $this->notification = 'Bill service deactivation submitted for approval.';
        $this->closeDrawer();
    }

    public function enumLabel(?string $value, string $fallback = '-'): string
    {
        return filled($value) ? str_replace('_', ' ', $value) : $fallback;
    }

    private function defaultProvider(): array
    {
        return [
            'name' => '',
            'code' => '',
            'type' => 'EXTERNAL_API',
            'baseUrl' => '',
            'credentialsRef' => '',
            'timeoutMillis' => 10000,
            'maxRetries' => 2,
            'retryBackoffMillis' => 500,
            'sandbox' => false,
            'supportsReferenceValidation' => false,
            'callbackSecretRef' => '',
        ];
    }

    private function providerRules(string $key, bool $creating): array
    {
        $rules = [
            "{$key}.name" => 'required|string|max:200',
            "{$key}.baseUrl" => 'nullable|string|max:500',
            "{$key}.credentialsRef" => 'nullable|string|max:200',
            "{$key}.timeoutMillis" => 'required|integer|between:100,60000',
            "{$key}.maxRetries" => 'required|integer|between:0,10',
            "{$key}.retryBackoffMillis" => 'required|integer|between:0,30000',
            "{$key}.sandbox" => 'boolean',
            "{$key}.supportsReferenceValidation" => 'boolean',
            "{$key}.callbackSecretRef" => 'nullable|string|max:200',
        ];

        if ($creating) {
            $rules["{$key}.code"] = 'required|string|max:60';
            $rules["{$key}.type"] = 'required|in:' . implode(',', $this->providerTypes);
        }

        return $rules;
    }

    private function serviceRules(string $key, bool $creating): array
    {
        $rules = [
            "{$key}.providerId" => 'required|string',
            "{$key}.name" => 'required|string|max:200',
            "{$key}.category" => 'required|in:' . implode(',', $this->serviceCategories),
            "{$key}.minAmount" => 'nullable|integer|min:1',
            "{$key}.maxAmount" => 'nullable|integer|min:1',
        ];

        if ($creating) {
            $rules["{$key}.code"] = 'required|string|max:80';
        }

        return $rules;
    }

    public function render(): \Illuminate\View\View
    {
        $allProviders = MockDataService::serviceProviders();
        $providerNames = collect($allProviders)->mapWithKeys(fn($provider) => [$provider['id'] => $provider['name']])->all();

        return view('livewire.service-providers.service-provider-management', [
            'providers' => MockDataService::serviceProviders([
                'type' => $this->providerTypeFilter ?: null,
                'status' => $this->providerStatusFilter ?: null,
            ]),
            'allProviders' => $allProviders,
            'providerNames' => $providerNames,
            'services' => MockDataService::billServices($this->serviceProviderFilter ?: '', [
                'category' => $this->serviceCategoryFilter ?: null,
                'status' => $this->serviceStatusFilter ?: null,
            ]),
        ]);
    }
};
?>

<div>
    @if($notification)
        <div class="alert alert-success mb-4"><x-icon name="check" size="15" /> {{ $notification }}</div>
    @endif

    <div class="card">
        <div class="tabs">
            <button class="tab @if($tab==='providers') active @endif" wire:click="setTab('providers')">Service Providers</button>
            <button class="tab @if($tab==='services') active @endif" wire:click="setTab('services')">Bill Services</button>
        </div>

        <div class="filter-bar">
            @if($tab === 'providers')
                <select wire:model.live="providerTypeFilter" class="filter-select">
                    <option value="">All types</option>
                    @foreach($providerTypes as $type)
                        <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="providerStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($providerStatuses as $status)
                        <option value="{{ $status }}">{{ $this->enumLabel($status) }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-primary btn-sm" wire:click="openProviderCreateModal">
                    <x-icon name="plus" size="13" /> Create Provider
                </button>
            @else
                <select wire:model.live="serviceProviderFilter" class="filter-select">
                    <option value="">All providers</option>
                    @foreach($allProviders as $provider)
                        <option value="{{ $provider['id'] }}">{{ $provider['name'] }}</option>
                    @endforeach
                </select>
                <select wire:model.live="serviceCategoryFilter" class="filter-select">
                    <option value="">All categories</option>
                    @foreach($serviceCategories as $category)
                        <option value="{{ $category }}">{{ $this->enumLabel($category) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="serviceStatusFilter" class="filter-select">
                    <option value="">All statuses</option>
                    @foreach($serviceStatuses as $status)
                        <option value="{{ $status }}">{{ $this->enumLabel($status) }}</option>
                    @endforeach
                </select>
                <div class="flex-1"></div>
                <button class="btn btn-primary btn-sm" wire:click="openServiceCreateModal">
                    <x-icon name="plus" size="13" /> Create Service
                </button>
            @endif
        </div>

        @if($tab === 'providers')
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Provider</th>
                            <th>Type</th>
                            <th>Endpoint</th>
                            <th>Validation</th>
                            <th>Retries</th>
                            <th>Updated</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($providers as $provider)
                            <tr class="table-row-link" wire:click="selectProvider('{{ $provider['id'] }}')">
                                <td>
                                    <div class="font-medium">{{ $provider['name'] }}</div>
                                    <x-mono>{{ $provider['code'] }}</x-mono>
                                </td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($provider['type']) }}</span></td>
                                <td><x-mono>{{ $provider['baseUrl'] ?? 'Internal' }}</x-mono></td>
                                <td><x-badge :status="$provider['supportsReferenceValidation'] ? 'ACTIVE' : 'INACTIVE'" :label="$provider['supportsReferenceValidation'] ? 'Supported' : 'No validation'" /></td>
                                <td><x-mono>{{ $provider['maxRetries'] }} / {{ number_format($provider['retryBackoffMillis']) }}ms</x-mono></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($provider['updatedAt'])->format('d M Y') }}</x-mono></td>
                                <td><x-badge :status="$provider['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No service providers found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($providers) }} providers</span></div>
        @else
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Provider</th>
                            <th>Category</th>
                            <th>Minimum</th>
                            <th>Maximum</th>
                            <th>Created</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($services as $service)
                            <tr class="table-row-link" wire:click="selectService('{{ $service['id'] }}')">
                                <td>
                                    <div class="font-medium">{{ $service['name'] }}</div>
                                    <x-mono>{{ $service['code'] }}</x-mono>
                                </td>
                                <td><span class="text-xs font-medium">{{ $providerNames[$service['providerId']] ?? $service['providerId'] }}</span></td>
                                <td><span class="text-xs font-medium">{{ $this->enumLabel($service['category']) }}</span></td>
                                <td><x-amount :value="$service['minAmount'] ?? '-'" size="12" /></td>
                                <td><x-amount :value="$service['maxAmount'] ?? '-'" size="12" /></td>
                                <td><x-mono>{{ \Carbon\Carbon::parse($service['createdAt'])->format('d M Y') }}</x-mono></td>
                                <td><x-badge :status="$service['status']" /></td>
                            </tr>
                        @empty
                            <tr><td colspan="7"><div class="empty-state"><div class="empty-state-title">No bill services found</div></div></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="pagination"><span class="pagination-info">{{ count($services) }} services</span></div>
        @endif
    </div>

    @if($selectedProvider)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">{{ $selectedProvider['name'] }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selectedProvider['status']" />
                    <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selectedProvider['type']) }}</span>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Provider</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedProvider['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Code</span><span class="drawer-field-value">{{ $selectedProvider['code'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Sandbox</span><span class="drawer-field-value">{{ $selectedProvider['sandbox'] ? 'Yes' : 'No' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Reference Validation</span><span class="drawer-field-value">{{ $selectedProvider['supportsReferenceValidation'] ? 'Supported' : 'Not supported' }}</span></div>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Connection</div>
                    <div class="drawer-field"><span class="drawer-field-label">Base URL</span><span class="drawer-field-value">{{ $selectedProvider['baseUrl'] ?? '-' }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Timeout</span><span class="drawer-field-value">{{ number_format($selectedProvider['timeoutMillis']) }}ms</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Retries</span><span class="drawer-field-value">{{ $selectedProvider['maxRetries'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Backoff</span><span class="drawer-field-value">{{ number_format($selectedProvider['retryBackoffMillis']) }}ms</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedProvider['createdAt'])->format('d M Y, H:i') }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Updated</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedProvider['updatedAt'])->format('d M Y, H:i') }}</span></div>
                </div>
            </div>
            <div class="drawer-footer">
                <button class="btn btn-secondary btn-sm" wire:click="openProviderEditModal">Edit</button>
                <button class="btn btn-primary btn-sm" wire:click="openServiceCreateModal('{{ $selectedProvider['id'] }}')">Add Service</button>
                @if($selectedProvider['status'] === 'ACTIVE')
                    <button class="btn btn-warning btn-sm" wire:click="deactivateProvider">Deactivate</button>
                @else
                    <button class="btn btn-primary btn-sm" wire:click="activateProvider">Activate</button>
                @endif
            </div>
        </div>
    @endif

    @if($selectedService)
        <div class="drawer-overlay" wire:click="closeDrawer"></div>
        <div class="drawer">
            <div class="drawer-header">
                <span class="drawer-title">{{ $selectedService['name'] }}</span>
                <button class="modal-close" wire:click="closeDrawer"><x-icon name="x" size="18" /></button>
            </div>
            <div class="drawer-body">
                <div class="mb-4 flex gap-2">
                    <x-badge :status="$selectedService['status']" />
                    <span class="text-xs font-semibold text-[var(--text-secondary)]">{{ $this->enumLabel($selectedService['category']) }}</span>
                </div>
                <div class="drawer-section">
                    <div class="drawer-section-title">Bill Service</div>
                    <div class="drawer-field"><span class="drawer-field-label">ID</span><span class="drawer-field-value">{{ $selectedService['id'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Code</span><span class="drawer-field-value">{{ $selectedService['code'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Provider</span><span class="drawer-field-value">{{ $providerNames[$selectedService['providerId']] ?? $selectedService['providerId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Provider ID</span><span class="drawer-field-value">{{ $selectedService['providerId'] }}</span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Minimum</span><span class="drawer-field-value"><x-amount :value="$selectedService['minAmount'] ?? '-'" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Maximum</span><span class="drawer-field-value"><x-amount :value="$selectedService['maxAmount'] ?? '-'" size="12" /></span></div>
                    <div class="drawer-field"><span class="drawer-field-label">Created</span><span class="drawer-field-value">{{ \Carbon\Carbon::parse($selectedService['createdAt'])->format('d M Y, H:i') }}</span></div>
                </div>
            </div>
            <div class="drawer-footer">
                <button class="btn btn-secondary btn-sm" wire:click="openServiceEditModal">Edit</button>
                @if($selectedService['status'] === 'ACTIVE')
                    <button class="btn btn-warning btn-sm" wire:click="deactivateService">Deactivate</button>
                @else
                    <button class="btn btn-primary btn-sm" wire:click="activateService">Activate</button>
                @endif
            </div>
        </div>
    @endif

    @if($showProviderCreateModal || $showProviderEditModal)
        @php($providerFormKey = $showProviderCreateModal ? 'newProvider' : 'editProvider')
        <div class="modal-overlay" wire:click.self="$set('{{ $showProviderCreateModal ? 'showProviderCreateModal' : 'showProviderEditModal' }}', false)">
            <div class="modal modal-lg">
                <div class="modal-header">
                    <span class="modal-title">{{ $showProviderCreateModal ? 'Create Service Provider' : 'Edit Service Provider' }}</span>
                    <button class="modal-close" wire:click="$set('{{ $showProviderCreateModal ? 'showProviderCreateModal' : 'showProviderEditModal' }}', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                        <div>
                            <label class="form-label">Name <span class="form-required">*</span></label>
                            <input wire:model="{{ $providerFormKey }}.name" type="text" class="form-input" />
                        </div>
                        @if($showProviderCreateModal)
                            <div>
                                <label class="form-label">Code <span class="form-required">*</span></label>
                                <input wire:model="newProvider.code" type="text" class="form-input is-mono" />
                            </div>
                            <div>
                                <label class="form-label">Type <span class="form-required">*</span></label>
                                <select wire:model="newProvider.type" class="form-select">
                                    @foreach($providerTypes as $type)
                                        <option value="{{ $type }}">{{ $this->enumLabel($type) }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        <div class="@if(!$showProviderCreateModal) md:col-span-2 @endif">
                            <label class="form-label">Base URL</label>
                            <input wire:model="{{ $providerFormKey }}.baseUrl" type="text" class="form-input is-mono" />
                        </div>
                        <div>
                            <label class="form-label">Credentials Ref</label>
                            <input wire:model="{{ $providerFormKey }}.credentialsRef" type="text" class="form-input is-mono" />
                        </div>
                        <div>
                            <label class="form-label">Callback Secret Ref</label>
                            <input wire:model="{{ $providerFormKey }}.callbackSecretRef" type="text" class="form-input is-mono" />
                        </div>
                        <div>
                            <label class="form-label">Timeout Millis <span class="form-required">*</span></label>
                            <input wire:model="{{ $providerFormKey }}.timeoutMillis" type="number" min="100" max="60000" class="form-input is-mono" />
                        </div>
                        <div>
                            <label class="form-label">Max Retries <span class="form-required">*</span></label>
                            <input wire:model="{{ $providerFormKey }}.maxRetries" type="number" min="0" max="10" class="form-input is-mono" />
                        </div>
                        <div>
                            <label class="form-label">Retry Backoff Millis <span class="form-required">*</span></label>
                            <input wire:model="{{ $providerFormKey }}.retryBackoffMillis" type="number" min="0" max="30000" class="form-input is-mono" />
                        </div>
                        <div class="flex items-end gap-4 pb-2">
                            <label class="flex cursor-pointer items-center gap-2 text-xs">
                                <input wire:model="{{ $providerFormKey }}.sandbox" type="checkbox" />
                                <span>Sandbox</span>
                            </label>
                            <label class="flex cursor-pointer items-center gap-2 text-xs">
                                <input wire:model="{{ $providerFormKey }}.supportsReferenceValidation" type="checkbox" />
                                <span>Reference validation</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('{{ $showProviderCreateModal ? 'showProviderCreateModal' : 'showProviderEditModal' }}', false)">Cancel</button>
                    @if($showProviderCreateModal)
                        <button class="btn btn-primary btn-md" wire:click="createProvider">Submit for Approval</button>
                    @else
                        <button class="btn btn-primary btn-md" wire:click="updateProvider">Submit for Approval</button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if($showServiceCreateModal || $showServiceEditModal)
        @php($serviceFormKey = $showServiceCreateModal ? 'newService' : 'editService')
        <div class="modal-overlay" wire:click.self="$set('{{ $showServiceCreateModal ? 'showServiceCreateModal' : 'showServiceEditModal' }}', false)">
            <div class="modal">
                <div class="modal-header">
                    <span class="modal-title">{{ $showServiceCreateModal ? 'Create Bill Service' : 'Edit Bill Service' }}</span>
                    <button class="modal-close" wire:click="$set('{{ $showServiceCreateModal ? 'showServiceCreateModal' : 'showServiceEditModal' }}', false)"><x-icon name="x" size="18" /></button>
                </div>
                <div class="modal-body">
                    <div class="flex flex-col gap-3">
                        <div>
                            <label class="form-label">Provider <span class="form-required">*</span></label>
                            <select wire:model="{{ $serviceFormKey }}.providerId" class="form-select" @if($showServiceEditModal) disabled @endif>
                                @foreach($allProviders as $provider)
                                    <option value="{{ $provider['id'] }}">{{ $provider['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Name <span class="form-required">*</span></label>
                            <input wire:model="{{ $serviceFormKey }}.name" type="text" class="form-input" />
                        </div>
                        @if($showServiceCreateModal)
                            <div>
                                <label class="form-label">Code <span class="form-required">*</span></label>
                                <input wire:model="newService.code" type="text" class="form-input is-mono" />
                            </div>
                        @endif
                        <div>
                            <label class="form-label">Category <span class="form-required">*</span></label>
                            <select wire:model="{{ $serviceFormKey }}.category" class="form-select">
                                @foreach($serviceCategories as $category)
                                    <option value="{{ $category }}">{{ $this->enumLabel($category) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Min Amount</label>
                                <input wire:model="{{ $serviceFormKey }}.minAmount" type="number" min="1" class="form-input is-mono" />
                            </div>
                            <div>
                                <label class="form-label">Max Amount</label>
                                <input wire:model="{{ $serviceFormKey }}.maxAmount" type="number" min="1" class="form-input is-mono" />
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary btn-md" wire:click="$set('{{ $showServiceCreateModal ? 'showServiceCreateModal' : 'showServiceEditModal' }}', false)">Cancel</button>
                    @if($showServiceCreateModal)
                        <button class="btn btn-primary btn-md" wire:click="createService">Submit for Approval</button>
                    @else
                        <button class="btn btn-primary btn-md" wire:click="updateService">Submit for Approval</button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
