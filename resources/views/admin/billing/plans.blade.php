@extends('layouts.admin')

@section('title')
  Plans Management
@endsection

@section('content-header')
  <h1>Plans Management<small>Manage subscription plans and pricing.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Billing</li>
  </ol>
@endsection

@section('content')
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Subscription Plans</h3>
          <div class="box-tools pull-right">
            <div class="btn-group">
              <button type="button" class="btn btn-sm btn-default" id="gameServerType" data-type="game-server">Game Servers</button>
              <button type="button" class="btn btn-sm btn-default" id="vpsType" data-type="vps">VPS</button>
            </div>
          </div>
        </div>
        <div class="box-body">
          <div class="table-responsive">
            <table class="table table-hover" id="plansTable">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Name</th>
                  <th>Price</th>
                  <th>Sales %</th>
                  <th>First Month %</th>
                  <th>Interval</th>
                  <th>Memory</th>
                  <th>Active</th>
                  <th>Sort Order</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody id="plansTableBody">
                <tr>
                  <td colspan="10" class="text-center">Loading plans...</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Edit Plan Modal -->
  <div class="modal fade" id="editPlanModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h4 class="modal-title">Edit Plan</h4>
        </div>
        <form id="editPlanForm">
          <div class="modal-body">
            <input type="hidden" id="editPlanId">
            <div class="form-group">
              <label>Name <span class="text-red">*</span></label>
              <input type="text" id="editPlanName" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea id="editPlanDescription" class="form-control" rows="3"></textarea>
            </div>
            <div class="row">
              <div class="form-group col-md-4">
                <label>Price <span class="text-red">*</span></label>
                <input type="number" step="0.01" id="editPlanPrice" class="form-control" required>
              </div>
              <div class="form-group col-md-4">
                <label>Sales Percentage</label>
                <input type="number" step="0.01" min="0" max="100" id="editPlanSalesPercentage" class="form-control" placeholder="e.g., 20 for 20% off">
                <p class="help-block">Percentage discount for sales (0-100)</p>
              </div>
              <div class="form-group col-md-4">
                <label>First Month Sales %</label>
                <input type="number" step="0.01" min="0" max="100" id="editPlanFirstMonthSalesPercentage" class="form-control" placeholder="e.g., 50 for 50% off">
                <p class="help-block">Percentage discount for first month (0-100)</p>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-4">
                <label>Currency</label>
                <input type="text" id="editPlanCurrency" class="form-control" maxlength="3" required>
              </div>
              <div class="form-group col-md-4">
                <label>Interval</label>
                <select id="editPlanInterval" class="form-control" required>
                  <option value="month">Month</option>
                  <option value="quarter">Quarter</option>
                  <option value="half-year">Half Year</option>
                  <option value="year">Year</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>Sort Order</label>
                <input type="number" id="editPlanSortOrder" class="form-control" min="0">
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-3">
                <label>Memory (MB)</label>
                <input type="number" id="editPlanMemory" class="form-control" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>Disk (MB)</label>
                <input type="number" id="editPlanDisk" class="form-control" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>CPU (%)</label>
                <input type="number" id="editPlanCpu" class="form-control" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>IO</label>
                <input type="number" id="editPlanIo" class="form-control" min="10" max="1000">
              </div>
            </div>
            <div class="form-group">
              <div class="checkbox checkbox-primary">
                <input type="checkbox" id="editPlanIsActive" value="1">
                <label for="editPlanIsActive">Active</label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="editPlanSubmitButton">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection

@section('footer-scripts')
  @parent
  <script>
    let currentType = 'game-server';

    function loadPlans(type = 'game-server') {
      $.ajax({
        url: '/admin/billing/plans/list',
        method: 'GET',
        data: { type: type },
        success: function(response) {
          const plans = response.data;
          
          let html = '';
          if (plans.length === 0) {
            html = '<tr><td colspan="10" class="text-center">No plans found.</td></tr>';
          } else {
            plans.forEach(function(plan) {
              const salesPercent = plan.sales_percentage !== null ? plan.sales_percentage.toFixed(2) + '%' : '—';
              const firstMonthPercent = plan.first_month_sales_percentage !== null ? plan.first_month_sales_percentage.toFixed(2) + '%' : '—';
              const memory = plan.memory ? (plan.memory / 1024).toFixed(0) + ' GB' : '—';
              const activeBadge = plan.is_active ? '<span class="label label-success">Active</span>' : '<span class="label label-default">Inactive</span>';
              
              html += '<tr>';
              html += '<td>' + plan.id + '</td>';
              html += '<td><strong>' + plan.name + '</strong></td>';
              html += '<td>' + parseFloat(plan.price).toFixed(2) + ' ' + plan.currency + '</td>';
              html += '<td>' + salesPercent + '</td>';
              html += '<td>' + firstMonthPercent + '</td>';
              html += '<td>' + plan.interval + '</td>';
              html += '<td>' + memory + '</td>';
              html += '<td>' + activeBadge + '</td>';
              html += '<td>' + plan.sort_order + '</td>';
              html += '<td>';
              html += '<button class="btn btn-xs btn-primary editPlan" data-plan-id="' + plan.id + '">';
              html += '<i class="fa fa-edit"></i> Edit';
              html += '</button>';
              html += '</td>';
              html += '</tr>';
            });
          }
          
          $('#plansTableBody').html(html);
        },
        error: function(xhr) {
          $('#plansTableBody').html('<tr><td colspan="10" class="text-center text-red">Failed to load plans.</td></tr>');
        }
      });
    }

    $(document).ready(function() {
      // Load initial plans
      loadPlans(currentType);

      // Type switching
      $('#gameServerType, #vpsType').on('click', function() {
        currentType = $(this).data('type');
        $('#gameServerType, #vpsType').removeClass('btn-primary').addClass('btn-default');
        $(this).removeClass('btn-default').addClass('btn-primary');
        loadPlans(currentType);
      });
      $('#gameServerType').addClass('btn-primary').removeClass('btn-default');

      // Edit plan
      $(document).on('click', '.editPlan', function() {
        const planId = $(this).data('plan-id');
        
        $.ajax({
          url: '/admin/billing/plans/list',
          method: 'GET',
          data: { type: currentType },
          success: function(response) {
            const plan = response.data.find(p => p.id === planId);
            if (!plan) {
              swal('Error', 'Plan not found.', 'error');
              return;
            }

            $('#editPlanId').val(plan.id);
            $('#editPlanName').val(plan.name);
            $('#editPlanDescription').val(plan.description || '');
            $('#editPlanPrice').val(plan.price);
            $('#editPlanSalesPercentage').val(plan.sales_percentage || '');
            $('#editPlanFirstMonthSalesPercentage').val(plan.first_month_sales_percentage || '');
            $('#editPlanCurrency').val(plan.currency);
            $('#editPlanInterval').val(plan.interval);
            $('#editPlanMemory').val(plan.memory || '');
            $('#editPlanDisk').val(plan.disk || '');
            $('#editPlanCpu').val(plan.cpu || '');
            $('#editPlanIo').val(plan.io || '');
            $('#editPlanSortOrder').val(plan.sort_order);
            $('#editPlanIsActive').prop('checked', plan.is_active);

            $('#editPlanModal').modal('show');
          }
        });
      });

      // Submit edit form
      $('#editPlanForm').on('submit', function(e) {
        e.preventDefault();
        
        const planId = $('#editPlanId').val();
        const data = {
          name: $('#editPlanName').val(),
          description: $('#editPlanDescription').val(),
          price: parseFloat($('#editPlanPrice').val()),
          sales_percentage: $('#editPlanSalesPercentage').val() ? parseFloat($('#editPlanSalesPercentage').val()) : null,
          first_month_sales_percentage: $('#editPlanFirstMonthSalesPercentage').val() ? parseFloat($('#editPlanFirstMonthSalesPercentage').val()) : null,
          currency: $('#editPlanCurrency').val(),
          interval: $('#editPlanInterval').val(),
          memory: $('#editPlanMemory').val() ? parseInt($('#editPlanMemory').val()) : null,
          disk: $('#editPlanDisk').val() ? parseInt($('#editPlanDisk').val()) : null,
          cpu: $('#editPlanCpu').val() ? parseInt($('#editPlanCpu').val()) : null,
          io: $('#editPlanIo').val() ? parseInt($('#editPlanIo').val()) : null,
          sort_order: parseInt($('#editPlanSortOrder').val()) || 0,
          is_active: $('#editPlanIsActive').is(':checked'),
        };

        $('#editPlanSubmitButton').prop('disabled', true).text('Saving...');

        $.ajax({
          url: '/admin/billing/plans/' + planId,
          method: 'PATCH',
          contentType: 'application/json',
          data: JSON.stringify(data),
          headers: {
            'X-CSRF-Token': $('meta[name="_token"]').attr('content')
          },
          success: function(response) {
            swal({
              title: 'Success',
              text: 'Plan updated successfully.',
              type: 'success'
            }, function() {
              $('#editPlanModal').modal('hide');
              loadPlans(currentType);
            });
          },
          error: function(xhr) {
            let errorMsg = 'Failed to update plan.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
              errorMsg = xhr.responseJSON.errors.join(' ');
            }
            swal('Error', errorMsg, 'error');
          },
          complete: function() {
            $('#editPlanSubmitButton').prop('disabled', false).text('Save Changes');
          }
        });
      });
    });
  </script>
@endsection
