@extends('layouts.admin')

@section('title')
    {{ $node->name }}: Allocations
@endsection

@section('content-header')
    <h1>{{ $node->name }}<small>Control allocations available for servers on this node.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li><a href="{{ route('admin.nodes') }}">Nodes</a></li>
        <li><a href="{{ route('admin.nodes.view', $node->id) }}">{{ $node->name }}</a></li>
        <li class="active">Allocations</li>
    </ol>
@endsection

@section('content')
<div class="row">
    <div class="col-xs-12">
        <div class="nav-tabs-custom nav-tabs-floating">
            <ul class="nav nav-tabs">
                <li><a href="{{ route('admin.nodes.view', $node->id) }}">About</a></li>
                <li><a href="{{ route('admin.nodes.view.settings', $node->id) }}">Settings</a></li>
                <li><a href="{{ route('admin.nodes.view.configuration', $node->id) }}">Configuration</a></li>
                <li class="active"><a href="{{ route('admin.nodes.view.allocation', $node->id) }}">Allocation</a></li>
                <li><a href="{{ route('admin.nodes.view.servers', $node->id) }}">Servers</a></li>
            </ul>
        </div>
    </div>
</div>
<div class="row">
    <div class="col-sm-8">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title">Existing Allocations</h3>
            </div>
            <div class="box-body table-responsive no-padding" style="overflow-x: visible">
                <table class="table table-hover" style="margin-bottom:0;">
                    <tr>
                        <th>
                            <input type="checkbox" class="select-all-files hidden-xs" data-action="selectAll">
                        </th>
                        <th>IP Address <i class="fa fa-fw fa-minus-square" style="font-weight:normal;color:#d9534f;cursor:pointer;" data-toggle="modal" data-target="#allocationModal"></i></th>
                        <th>IP Alias</th>
                        <th>Port</th>
                        <th>Restrictions</th>
                        <th>Assigned To</th>
                        <th>
                            <div class="btn-group hidden-xs">
                                <button type="button" id="mass_actions" class="btn btn-sm btn-default dropdown-toggle disabled"
                                        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">Mass Actions <span class="caret"></span>
                                </button>
                                <ul class="dropdown-menu dropdown-massactions">
                                    <li><a href="#" id="selective-deletion" data-action="selective-deletion">Delete <i class="fa fa-fw fa-trash-o"></i></a></li>
                                </ul>
                            </div>
                        </th>
                    </tr>
                    @foreach($node->allocations as $allocation)
                        @php
                            $allocationRestrictions = isset($allocationsWithRestrictions) ? $allocationsWithRestrictions->firstWhere('id', $allocation->id) : null;
                            $allowedNests = $allocationRestrictions ? $allocationRestrictions->allowedNests->pluck('id')->toArray() : [];
                            $allowedEggs = $allocationRestrictions ? $allocationRestrictions->allowedEggs->pluck('id')->toArray() : [];
                            $restrictionTypeLabel = $allocationRestrictions ? ($allocationRestrictions->restriction_type ?? 'none') : 'none';
                        @endphp
                        <tr>
                            <td class="middle min-size" data-identifier="type">
                                @if(is_null($allocation->server_id))
                                <input type="checkbox" class="select-file hidden-xs" data-action="addSelection">
                                @else
                                <input disabled="disabled" type="checkbox" class="select-file hidden-xs" data-action="addSelection">
                                @endif
                            </td>
                            <td class="col-sm-2 middle" data-identifier="ip">{{ $allocation->ip }}</td>
                            <td class="col-sm-2 middle">
                                <input class="form-control input-sm" type="text" value="{{ $allocation->ip_alias }}" data-action="set-alias" data-id="{{ $allocation->id }}" placeholder="none" />
                                <span class="input-loader"><i class="fa fa-refresh fa-spin fa-fw"></i></span>
                            </td>
                            <td class="col-sm-1 middle" data-identifier="port">{{ $allocation->port }}</td>
                            <td class="col-sm-2 middle">
                                @php
                                $restrictionTypeLabel = $allocationRestrictions ? ($allocationRestrictions->restriction_type ?? 'none') : 'none';
                            @endphp
                            @if($restrictionTypeLabel === 'none')
                                <span class="label label-success" data-toggle="tooltip" title="No restrictions - available to all nests/eggs">
                                    <i class="fa fa-unlock"></i> Open
                                </span>
                            @elseif($restrictionTypeLabel === 'whitelist')
                                <span class="label label-info" data-toggle="tooltip" title="Whitelist - Only selected nests/eggs allowed">
                                    <i class="fa fa-check-circle"></i> Whitelist
                                </span>
                            @else
                                <span class="label label-warning" data-toggle="tooltip" title="Blacklist - Selected nests/eggs blocked">
                                    <i class="fa fa-ban"></i> Blacklist
                                </span>
                            @endif
                                <button 
                                    class="btn btn-xs btn-default" 
                                    data-toggle="modal" 
                                    data-target="#restrictionModal{{ $allocation->id }}"
                                    style="margin-left: 5px;"
                                >
                                    <i class="fa fa-cog"></i> Configure
                                </button>
                            </td>
                            <td class="col-sm-2 middle">
                                @if(! is_null($allocation->server))
                                    <a href="{{ route('admin.servers.view', $allocation->server_id) }}">{{ $allocation->server->name }}</a>
                                @endif
                            </td>
                            <td class="col-sm-1 middle">
                                @if(is_null($allocation->server_id))
                                    <button data-action="deallocate" data-id="{{ $allocation->id }}" class="btn btn-sm btn-danger"><i class="fa fa-trash-o"></i></button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </table>
            </div>
            @if($node->allocations->hasPages())
                <div class="box-footer text-center">
                    {{ $node->allocations->render() }}
                </div>
            @endif
        </div>
    </div>
    <div class="col-sm-4">
        <form action="{{ route('admin.nodes.view.allocation', $node->id) }}" method="POST">
            <div class="box box-success">
                <div class="box-header with-border">
                    <h3 class="box-title">Assign New Allocations</h3>
                </div>
                <div class="box-body">
                    <div class="form-group">
                        <label for="pAllocationIP" class="control-label">IP Address</label>
                        <div>
                            <select class="form-control" name="allocation_ip" id="pAllocationIP" multiple>
                                @foreach($allocations as $allocation)
                                    <option value="{{ $allocation->ip }}">{{ $allocation->ip }}</option>
                                @endforeach
                            </select>
                            <p class="text-muted small">Enter an IP address to assign ports to here.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pAllocationIP" class="control-label">IP Alias</label>
                        <div>
                            <input type="text" id="pAllocationAlias" class="form-control" name="allocation_alias" placeholder="alias" />
                            <p class="text-muted small">If you would like to assign a default alias to these allocations enter it here.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="pAllocationPorts" class="control-label">Ports</label>
                        <div>
                            <select class="form-control" name="allocation_ports[]" id="pAllocationPorts" multiple></select>
                            <p class="text-muted small">Enter individual ports or port ranges here separated by commas or spaces.</p>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="control-label">Allocation Restrictions</label>
                        <div class="radio radio-primary">
                            <input type="radio" id="restrictionTypeNone" name="restriction_type" value="none" checked>
                            <label for="restrictionTypeNone"><strong>None</strong> - Available to all nests/eggs (default)</label>
                        </div>
                        <div class="radio radio-primary">
                            <input type="radio" id="restrictionTypeWhitelist" name="restriction_type" value="whitelist">
                            <label for="restrictionTypeWhitelist"><strong>Whitelist</strong> - Only allow selected nests/eggs</label>
                        </div>
                        <div class="radio radio-primary">
                            <input type="radio" id="restrictionTypeBlacklist" name="restriction_type" value="blacklist">
                            <label for="restrictionTypeBlacklist"><strong>Blacklist</strong> - Block selected nests/eggs</label>
                        </div>
                    </div>
                    <div class="form-group" id="restrictionNestsGroup" style="display:none;">
                        <label for="pRestrictionNests" class="control-label">Nests</label>
                        <div>
                            <select class="form-control" name="restriction_nests[]" id="pRestrictionNests" multiple></select>
                            <p class="text-muted small">Select which nests to whitelist or blacklist.</p>
                        </div>
                    </div>
                    <div class="form-group" id="restrictionEggsGroup" style="display:none;">
                        <label for="pRestrictionEggs" class="control-label">Eggs</label>
                        <div>
                            <select class="form-control" name="restriction_eggs[]" id="pRestrictionEggs" multiple></select>
                            <p class="text-muted small">Select which eggs to whitelist or blacklist.</p>
                        </div>
                    </div>
                </div>
                <div class="box-footer">
                    {!! csrf_field() !!}
                    <button type="submit" class="btn btn-success btn-sm pull-right">Submit</button>
                </div>
            </div>
        </form>
    </div>
</div>
<div class="modal fade" id="allocationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title">Delete Allocations for IP Block</h4>
            </div>
            <form action="{{ route('admin.nodes.view.allocation.removeBlock', $node->id) }}" method="POST">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">
                            <select class="form-control" name="ip">
                                @foreach($allocations as $allocation)
                                    <option value="{{ $allocation->ip }}">{{ $allocation->ip }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    {{{ csrf_field() }}}
                    <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-danger">Delete Allocations</button>
                </div>
            </form>
        </div>
    </div>
</div>

@foreach($node->allocations as $allocation)
    @php
        $allocationRestrictions = isset($allocationsWithRestrictions) ? $allocationsWithRestrictions->firstWhere('id', $allocation->id) : null;
        $allowedNests = $allocationRestrictions ? $allocationRestrictions->allowedNests->pluck('id')->toArray() : [];
        $allowedEggs = $allocationRestrictions ? $allocationRestrictions->allowedEggs->pluck('id')->toArray() : [];
        $restrictionType = $allocationRestrictions ? ($allocationRestrictions->restriction_type ?? 'none') : 'none';
    @endphp
    <div class="modal fade" id="restrictionModal{{ $allocation->id }}" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Configure Restrictions for {{ $allocation->ip }}:{{ $allocation->port }}</h4>
                </div>
                <form action="{{ route('admin.nodes.view.allocation.restrictions', ['node' => $node->id, 'allocation' => $allocation->id]) }}" method="POST">
                    <div class="modal-body">
                        <div class="form-group">
                            <label class="control-label">Restriction Type</label>
                            <div class="radio radio-primary">
                                <input type="radio" id="modalRestrictionNone{{ $allocation->id }}" name="restriction_type" value="none" {{ $restrictionType === 'none' ? 'checked' : '' }}>
                                <label for="modalRestrictionNone{{ $allocation->id }}"><strong>None</strong> - Available to all nests/eggs</label>
                            </div>
                            <div class="radio radio-primary">
                                <input type="radio" id="modalRestrictionWhitelist{{ $allocation->id }}" name="restriction_type" value="whitelist" {{ $restrictionType === 'whitelist' ? 'checked' : '' }}>
                                <label for="modalRestrictionWhitelist{{ $allocation->id }}"><strong>Whitelist</strong> - Only allow selected nests/eggs</label>
                            </div>
                            <div class="radio radio-primary">
                                <input type="radio" id="modalRestrictionBlacklist{{ $allocation->id }}" name="restriction_type" value="blacklist" {{ $restrictionType === 'blacklist' ? 'checked' : '' }}>
                                <label for="modalRestrictionBlacklist{{ $allocation->id }}"><strong>Blacklist</strong> - Block selected nests/eggs</label>
                            </div>
                        </div>
                        
                        <div class="form-group" id="modalNestsGroup{{ $allocation->id }}" style="display:{{ $restrictionType === 'none' ? 'none' : 'block' }};">
                            <label for="modalNests{{ $allocation->id }}">Nests</label>
                            <select class="form-control" name="nests[]" id="modalNests{{ $allocation->id }}" multiple style="width: 100%;">
                                @foreach($nests as $nest)
                                    <option value="{{ $nest->id }}" {{ in_array($nest->id, $allowedNests) ? 'selected' : '' }}>
                                        {{ $nest->name }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="help-block">Select which nests to whitelist or blacklist.</p>
                        </div>

                        <div class="form-group" id="modalEggsGroup{{ $allocation->id }}" style="display:{{ $restrictionType === 'none' ? 'none' : 'block' }};">
                            <label for="modalEggs{{ $allocation->id }}">Eggs</label>
                            <select class="form-control" name="eggs[]" id="modalEggs{{ $allocation->id }}" multiple style="width: 100%;">
                                @foreach($nests as $nest)
                                    <optgroup label="{{ $nest->name }}">
                                        @foreach($nest->eggs as $egg)
                                            <option value="{{ $egg->id }}" {{ in_array($egg->id, $allowedEggs) ? 'selected' : '' }}>
                                                {{ $egg->name }}
                                            </option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <p class="help-block">Select which eggs to whitelist or blacklist.</p>
                        </div>
                    </div>
                    <div class="modal-footer">
                        {{{ csrf_field() }}}
                        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
                        <button type="submit" class="btn btn-primary">Save Restrictions</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    (function() {
        var allocationId = {{ $allocation->id }};
        var modal = $('#restrictionModal' + allocationId);
        var nestsSelect = $('#modalNests' + allocationId);
        var eggsSelect = $('#modalEggs' + allocationId);
        var select2Initialized = false;
        
        function initializeSelect2() {
            // Skip if already initialized
            if (select2Initialized && nestsSelect.hasClass('select2-hidden-accessible')) {
                return;
            }
            
            // Destroy existing select2 if any
            try {
                if (nestsSelect.hasClass('select2-hidden-accessible')) {
                    nestsSelect.select2('destroy');
                }
                if (eggsSelect.hasClass('select2-hidden-accessible')) {
                    eggsSelect.select2('destroy');
                }
            } catch(e) {}
            
            // Initialize select2 with existing options (select already has options in HTML)
            nestsSelect.select2({
                placeholder: 'Select nests...',
                allowClear: true,
                dropdownParent: modal,
                width: '100%'
            });
            
            eggsSelect.select2({
                placeholder: 'Select eggs...',
                allowClear: true,
                dropdownParent: modal,
                width: '100%'
            });
            
            // Set selected values if they exist
            @if(!empty($allowedNests))
            nestsSelect.val([{{ implode(',', $allowedNests) }}]).trigger('change');
            @endif
            
            @if(!empty($allowedEggs))
            eggsSelect.val([{{ implode(',', $allowedEggs) }}]).trigger('change');
            @endif
            
            select2Initialized = true;
        }
        
        function toggleRestrictionFields(restrictionType) {
            var nestsGroup = $('#modalNestsGroup' + allocationId);
            var eggsGroup = $('#modalEggsGroup' + allocationId);
            
            if (restrictionType === 'none') {
                nestsGroup.hide();
                eggsGroup.hide();
                
                // Clean up select2 when hiding
                try {
                    if (nestsSelect.hasClass('select2-hidden-accessible')) {
                        nestsSelect.select2('destroy');
                    }
                    if (eggsSelect.hasClass('select2-hidden-accessible')) {
                        eggsSelect.select2('destroy');
                    }
                } catch(e) {}
                select2Initialized = false;
            } else {
                // Show immediately (no animation)
                nestsGroup.show();
                eggsGroup.show();
                
                // Initialize select2 immediately when fields are shown
                setTimeout(function() {
                    initializeSelect2();
                }, 10);
            }
        }
        
        // When modal is shown - attach event handlers and initialize
        modal.on('show.bs.modal', function() {
            // Attach change handler directly to radio buttons in this modal
            modal.find('input[name="restriction_type"]').off('change.modal-restriction').on('change.modal-restriction', function() {
                var restrictionType = $(this).val();
                toggleRestrictionFields(restrictionType);
            });
        });
        
        modal.on('shown.bs.modal', function() {
            var checkedRadio = modal.find('input[name="restriction_type"]:checked');
            var restrictionType = checkedRadio.length ? checkedRadio.val() : 'none';
            
            // Show/hide fields based on current selection
            toggleRestrictionFields(restrictionType);
        });
        
        // Cleanup when modal is hidden
        modal.on('hidden.bs.modal', function() {
            try {
                if (nestsSelect.hasClass('select2-hidden-accessible')) {
                    nestsSelect.select2('destroy');
                }
                if (eggsSelect.hasClass('select2-hidden-accessible')) {
                    eggsSelect.select2('destroy');
                }
            } catch(e) {}
            select2Initialized = false;
            
            // Remove event handlers
            modal.find('input[name="restriction_type"]').off('change.modal-restriction');
        });
    })();
    </script>
@endforeach
@endsection

@section('footer-scripts')
    @parent
    <script>
    $('[data-action="addSelection"]').on('click', function () {
        updateMassActions();
    });

    $('[data-action="selectAll"]').on('click', function () {
        $('input.select-file').not(':disabled').prop('checked', function (i, val) {
            return !val;
        });

        updateMassActions();
    });

    $('[data-action="selective-deletion"]').on('mousedown', function () {
        deleteSelected();
    });

    $('#pAllocationIP').select2({
        tags: true,
        maximumSelectionLength: 1,
        selectOnClose: true,
        tokenSeparators: [',', ' '],
    });

    $('#pAllocationPorts').select2({
        tags: true,
        selectOnClose: true,
        tokenSeparators: [',', ' '],
    });

    // Initialize restriction nests select2 with all nests
    @if(isset($nests))
    var nestsData = [
        @foreach($nests as $nest)
        {
            id: {{ $nest->id }},
            text: '{{ $nest->name }}',
            nest: true
        },
        @endforeach
    ];
    $('#pRestrictionNests').select2({
        data: nestsData,
        placeholder: 'Select nests...',
        allowClear: true,
    });

    // Initialize restriction eggs select2 with all eggs grouped by nest
    var eggsData = [
        @foreach($nests as $nest)
            @foreach($nest->eggs as $egg)
            {
                id: {{ $egg->id }},
                text: '{{ $nest->name }} - {{ $egg->name }}',
                egg: true
            },
            @endforeach
        @endforeach
    ];
    $('#pRestrictionEggs').select2({
        data: eggsData,
        placeholder: 'Select eggs...',
        allowClear: true,
    });
    @endif

    // Show/hide restriction fields based on restriction type (only for creation form, not modals)
    $('#restrictionNestsGroup').closest('form').find('input[name="restriction_type"]').on('change', function() {
        // Only handle if this is in the creation form (not a modal)
        if ($(this).closest('.modal').length === 0) {
            var restrictionType = $(this).val();
            if (restrictionType === 'none') {
                $('#restrictionNestsGroup').slideUp();
                $('#restrictionEggsGroup').slideUp();
                $('#pRestrictionNests').val(null).trigger('change');
                $('#pRestrictionEggs').val(null).trigger('change');
            } else {
                $('#restrictionNestsGroup').slideDown();
                $('#restrictionEggsGroup').slideDown();
            }
        }
    });

    $('button[data-action="deallocate"]').click(function (event) {
        event.preventDefault();
        var element = $(this);
        var allocation = $(this).data('id');
        swal({
            title: '',
            text: 'Are you sure you want to delete this allocation?',
            type: 'warning',
            showCancelButton: true,
            allowOutsideClick: true,
            closeOnConfirm: false,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#d9534f',
            showLoaderOnConfirm: true
        }, function () {
            $.ajax({
                method: 'DELETE',
                url: '/admin/nodes/view/' + {{ $node->id }} + '/allocation/remove/' + allocation,
                headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') },
            }).done(function (data) {
                element.parent().parent().addClass('warning').delay(100).fadeOut();
                swal({ type: 'success', title: 'Port Deleted!' });
            }).fail(function (jqXHR) {
                console.error(jqXHR);
                swal({
                    title: 'Whoops!',
                    text: jqXHR.responseJSON.error,
                    type: 'error'
                });
            });
        });
    });

    var typingTimer;
    $('input[data-action="set-alias"]').keyup(function () {
        clearTimeout(typingTimer);
        $(this).parent().removeClass('has-error has-success');
        typingTimer = setTimeout(sendAlias, 250, $(this));
    });

    var fadeTimers = [];
    function sendAlias(element) {
        element.parent().find('.input-loader').show();
        clearTimeout(fadeTimers[element.data('id')]);
        $.ajax({
            method: 'POST',
            url: '/admin/nodes/view/' + {{ $node->id }} + '/allocation/alias',
            headers: { 'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content') },
            data: {
                alias: element.val(),
                allocation_id: element.data('id'),
            }
        }).done(function () {
            element.parent().addClass('has-success');
        }).fail(function (jqXHR) {
            console.error(jqXHR);
            element.parent().addClass('has-error');
        }).always(function () {
            element.parent().find('.input-loader').hide();
            fadeTimers[element.data('id')] = setTimeout(clearHighlight, 2500, element);
        });
    }

    function clearHighlight(element) {
        element.parent().removeClass('has-error has-success');
    }

    function updateMassActions() {
        if ($('input.select-file:checked').length > 0) {
            $('#mass_actions').removeClass('disabled');
        } else {
            $('#mass_actions').addClass('disabled');
        }
    }

    function deleteSelected() {
        var selectedIds = [];
        var selectedItems = [];
        var selectedItemsElements = [];

        $('input.select-file:checked').each(function () {
            var $parent = $($(this).closest('tr'));
            var id = $parent.find('[data-action="deallocate"]').data('id');
            var $ip = $parent.find('td[data-identifier="ip"]');
            var $port = $parent.find('td[data-identifier="port"]');
            var block = `${$ip.text()}:${$port.text()}`;

            selectedIds.push({
                id: id
            });
            selectedItems.push(block);
            selectedItemsElements.push($parent);
        });

        if (selectedItems.length !== 0) {
            var formattedItems = "";
            var i = 0;
            $.each(selectedItems, function (key, value) {
                formattedItems += ("<code>" + value + "</code>, ");
                i++;
                return i < 5;
            });

            formattedItems = formattedItems.slice(0, -2);
            if (selectedItems.length > 5) {
                formattedItems += ', and ' + (selectedItems.length - 5) + ' other(s)';
            }

            swal({
                type: 'warning',
                title: '',
                text: 'Are you sure you want to delete the following allocations: ' + formattedItems + '?',
                html: true,
                showCancelButton: true,
                showConfirmButton: true,
                closeOnConfirm: false,
                showLoaderOnConfirm: true
            }, function () {
                $.ajax({
                    method: 'DELETE',
                    url: '/admin/nodes/view/' + {{ $node->id }} + '/allocations',
                    headers: {'X-CSRF-TOKEN': $('meta[name="_token"]').attr('content')},
                    data: JSON.stringify({
                        allocations: selectedIds
                    }),
                    contentType: 'application/json',
                    processData: false
                }).done(function () {
                    $('#file_listing input:checked').each(function () {
                        $(this).prop('checked', false);
                    });

                    $.each(selectedItemsElements, function () {
                        $(this).addClass('warning').delay(200).fadeOut();
                    });

                    swal({
                        type: 'success',
                        title: 'Allocations Deleted'
                    });
                }).fail(function (jqXHR) {
                    console.error(jqXHR);
                    swal({
                        type: 'error',
                        title: 'Whoops!',
                        html: true,
                        text: 'An error occurred while attempting to delete these allocations. Please try again.',
                    });
                });
            });
        } else {
            swal({
                type: 'warning',
                title: '',
                text: 'Please select allocation(s) to delete.',
            });
        }
    }
    </script>
@endsection
