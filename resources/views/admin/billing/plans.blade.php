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
            <div class="btn-group" style="margin-right: 10px;" id="categoryButtons">
              <!-- Categories will be loaded dynamically -->
            </div>
            <button type="button" class="btn btn-sm btn-info" id="manageCategoriesButton" style="margin-right: 10px;">
              <i class="fa fa-tags"></i> Manage Categories
            </button>
            <button type="button" class="btn btn-sm btn-warning" id="manageBillingDiscountsButton" style="margin-right: 10px;">
              <i class="fa fa-percent"></i> Billing Discounts
            </button>
            <button type="button" class="btn btn-sm btn-success" id="createPlanButton">
              <i class="fa fa-plus"></i> Create Plan
            </button>
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

  <!-- Create Plan Modal -->
  <div class="modal fade" id="createPlanModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h4 class="modal-title">Create New Plan</h4>
        </div>
        <form id="createPlanForm">
          <div class="modal-body">
            <div class="form-group">
              <label>Name <span class="text-red">*</span></label>
              <input type="text" id="createPlanName" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Description</label>
              <textarea id="createPlanDescription" class="form-control" rows="3"></textarea>
            </div>
            <div class="row">
              <div class="form-group col-md-4">
                <label>Price <span class="text-red">*</span></label>
                <input type="number" step="0.01" id="createPlanPrice" class="form-control" required>
              </div>
              <div class="form-group col-md-4">
                <label>Sales Percentage</label>
                <input type="number" step="0.01" min="0" max="100" id="createPlanSalesPercentage" class="form-control" placeholder="e.g., 20 for 20% off">
                <p class="help-block">Percentage discount for sales (0-100)</p>
              </div>
              <div class="form-group col-md-4">
                <label>First Month Sales %</label>
                <input type="number" step="0.01" min="0" max="100" id="createPlanFirstMonthSalesPercentage" class="form-control" placeholder="e.g., 50 for 50% off">
                <p class="help-block">Percentage discount for first month (0-100)</p>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-4">
                <label>Currency <span class="text-red">*</span></label>
                <input type="text" id="createPlanCurrency" class="form-control" maxlength="3" value="USD" required>
              </div>
              <div class="form-group col-md-4">
                <label>Interval <span class="text-red">*</span></label>
                <select id="createPlanInterval" class="form-control" required>
                  <option value="month">Month</option>
                  <option value="quarter">Quarter</option>
                  <option value="half-year">Half Year</option>
                  <option value="year">Year</option>
                </select>
              </div>
              <div class="form-group col-md-4">
                <label>Type <span class="text-red">*</span></label>
                <select id="createPlanType" class="form-control" required>
                  <!-- Options will be populated dynamically -->
                </select>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-3">
                <label>Memory (MB)</label>
                <input type="number" id="createPlanMemory" class="form-control" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>Disk (MB)</label>
                <input type="number" id="createPlanDisk" class="form-control" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>CPU (%)</label>
                <input type="number" id="createPlanCpu" class="form-control" min="0">
              </div>
              <div class="form-group col-md-3">
                <label>IO</label>
                <input type="number" id="createPlanIo" class="form-control" min="10" max="1000">
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-6">
                <label>Swap (MB)</label>
                <input type="number" id="createPlanSwap" class="form-control" min="-1">
              </div>
              <div class="form-group col-md-6">
                <label>Sort Order</label>
                <input type="number" id="createPlanSortOrder" class="form-control" min="0" value="0">
              </div>
            </div>
            <div class="form-group">
              <div class="checkbox checkbox-primary">
                <input type="checkbox" id="createPlanIsActive" value="1" checked>
                <label for="createPlanIsActive">Active</label>
              </div>
            </div>
            <div class="form-group">
              <div class="checkbox checkbox-primary">
                <input type="checkbox" id="createPlanIsMostPopular" value="1">
                <label for="createPlanIsMostPopular">Most Popular</label>
                <p class="help-block">Mark this plan as the "Most Popular" option on the hosting page</p>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success" id="createPlanSubmitButton">Create Plan</button>
          </div>
        </form>
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
            <div class="row">
              <div class="form-group col-md-6">
                <label>Swap (MB)</label>
                <input type="number" id="editPlanSwap" class="form-control" min="-1">
              </div>
              <div class="form-group col-md-6">
                <label>Sort Order</label>
                <input type="number" id="editPlanSortOrder" class="form-control" min="0">
              </div>
            </div>
            <div class="form-group">
              <div class="checkbox checkbox-primary">
                <input type="checkbox" id="editPlanIsActive" value="1">
                <label for="editPlanIsActive">Active</label>
              </div>
            </div>
            <div class="form-group">
              <div class="checkbox checkbox-primary">
                <input type="checkbox" id="editPlanIsMostPopular" value="1">
                <label for="editPlanIsMostPopular">Most Popular</label>
                <p class="help-block">Mark this plan as the "Most Popular" option on the hosting page</p>
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
              const escapedPlanName = plan.name.replace(/"/g, '&quot;').replace(/'/g, '&#39;');
              html += '<button class="btn btn-xs btn-primary editPlan" data-plan-id="' + plan.id + '" style="margin-right: 5px;">';
              html += '<i class="fa fa-edit"></i> Edit';
              html += '</button>';
              html += '<button class="btn btn-xs btn-danger deletePlan" data-plan-id="' + plan.id + '" data-plan-name="' + escapedPlanName + '">';
              html += '<i class="fa fa-trash"></i> Delete';
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

    let categories = [];
    
    // Populate type dropdown
    function populateTypeDropdown() {
      let html = '';
      categories.forEach(function(cat) {
        html += '<option value="' + cat.slug + '">' + cat.name + '</option>';
      });
      $('#createPlanType').html(html);
      $('#createPlanType').val(currentType);
    }

    function loadCategories() {
      $.ajax({
        url: '/admin/billing/plans/categories',
        method: 'GET',
        success: function(response) {
          categories = response.data || [];
          renderCategoryButtons();
          renderCategoriesList();
        },
        error: function() {
          // Default categories
          categories = [
            { name: 'Game', slug: 'game-server' },
            { name: 'VPS', slug: 'vps' }
          ];
          renderCategoryButtons();
          renderCategoriesList();
        }
      });
    }

    function renderCategoryButtons() {
      let html = '';
      categories.forEach(function(cat, index) {
        const isActive = index === 0 ? 'btn-primary' : 'btn-default';
        html += '<button type="button" class="btn btn-sm ' + isActive + ' category-btn" data-type="' + cat.slug + '">' + cat.name + '</button>';
      });
      $('#categoryButtons').html(html);
      
      // Set first category as active
      if (categories.length > 0) {
        currentType = categories[0].slug;
        loadPlans(currentType);
        populateTypeDropdown();
      }
    }

    function renderCategoriesList() {
      let html = '';
      categories.forEach(function(cat, index) {
        html += '<div class="category-item" data-index="' + index + '" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">';
        html += '<div class="row">';
        html += '<div class="col-md-5"><label>Display Name</label><input type="text" class="form-control category-name" value="' + (cat.name || '') + '" /></div>';
        html += '<div class="col-md-5"><label>Slug</label><input type="text" class="form-control category-slug" value="' + (cat.slug || '') + '" /></div>';
        html += '<div class="col-md-2"><label>&nbsp;</label><br /><button type="button" class="btn btn-sm btn-danger remove-category"><i class="fa fa-trash"></i></button></div>';
        html += '</div></div>';
      });
      $('#categoriesList').html(html);
    }

    $(document).ready(function() {
      // Load categories first, which will then load plans
      loadCategories();

      // Open create plan modal
      $('#createPlanButton').on('click', function() {
        // Reset form
        $('#createPlanForm')[0].reset();
        $('#createPlanCurrency').val('USD');
        $('#createPlanInterval').val('month');
        populateTypeDropdown();
        $('#createPlanSortOrder').val('0');
        $('#createPlanIsActive').prop('checked', true);
        $('#createPlanModal').modal('show');
      });

      // Submit create form
      $('#createPlanForm').on('submit', function(e) {
        e.preventDefault();
        
        const data = {
          name: $('#createPlanName').val(),
          description: $('#createPlanDescription').val(),
          price: parseFloat($('#createPlanPrice').val()),
          sales_percentage: $('#createPlanSalesPercentage').val() ? parseFloat($('#createPlanSalesPercentage').val()) : null,
          first_month_sales_percentage: $('#createPlanFirstMonthSalesPercentage').val() ? parseFloat($('#createPlanFirstMonthSalesPercentage').val()) : null,
          currency: $('#createPlanCurrency').val(),
          interval: $('#createPlanInterval').val(),
          type: $('#createPlanType').val(),
          memory: $('#createPlanMemory').val() ? parseInt($('#createPlanMemory').val()) : null,
          disk: $('#createPlanDisk').val() ? parseInt($('#createPlanDisk').val()) : null,
          cpu: $('#createPlanCpu').val() ? parseInt($('#createPlanCpu').val()) : null,
          io: $('#createPlanIo').val() ? parseInt($('#createPlanIo').val()) : null,
          swap: $('#createPlanSwap').val() ? parseInt($('#createPlanSwap').val()) : null,
          sort_order: parseInt($('#createPlanSortOrder').val()) || 0,
          is_active: $('#createPlanIsActive').is(':checked'),
          is_most_popular: $('#createPlanIsMostPopular').is(':checked'),
        };

        $('#createPlanSubmitButton').prop('disabled', true).text('Creating...');

        $.ajax({
          url: '/admin/billing/plans',
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify(data),
          headers: {
            'X-CSRF-Token': $('meta[name="_token"]').attr('content')
          },
          success: function(response) {
            swal({
              title: 'Success',
              text: 'Plan created successfully.',
              type: 'success'
            }, function() {
              $('#createPlanModal').modal('hide');
              loadPlans(currentType);
            });
          },
          error: function(xhr) {
            let errorMsg = 'Failed to create plan.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
              errorMsg = xhr.responseJSON.errors.join(' ');
            }
            swal('Error', errorMsg, 'error');
          },
          complete: function() {
            $('#createPlanSubmitButton').prop('disabled', false).text('Create Plan');
          }
        });
      });

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
            $('#editPlanSwap').val(plan.swap || '');
            $('#editPlanSortOrder').val(plan.sort_order);
            $('#editPlanIsActive').prop('checked', plan.is_active);
            $('#editPlanIsMostPopular').prop('checked', plan.is_most_popular);

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
          swap: $('#editPlanSwap').val() ? parseInt($('#editPlanSwap').val()) : null,
          sort_order: parseInt($('#editPlanSortOrder').val()) || 0,
          is_active: $('#editPlanIsActive').is(':checked'),
          is_most_popular: $('#editPlanIsMostPopular').is(':checked'),
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

      // Delete plan
      $(document).on('click', '.deletePlan', function() {
        const planId = $(this).data('plan-id');
        const planName = $(this).data('plan-name');
        
        swal({
          title: 'Are you sure?',
          text: 'You are about to delete the plan "' + planName + '". This action cannot be undone!',
          type: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#d33',
          cancelButtonColor: '#3085d6',
          confirmButtonText: 'Yes, delete it!',
          cancelButtonText: 'Cancel',
          closeOnConfirm: false,
          showLoaderOnConfirm: true
        }, function(isConfirm) {
          if (isConfirm) {
            $.ajax({
              url: '/admin/billing/plans/' + planId,
              method: 'DELETE',
              headers: {
                'X-CSRF-Token': $('meta[name="_token"]').attr('content')
              },
              success: function(response) {
                swal({
                  title: 'Deleted!',
                  text: 'The plan has been deleted successfully.',
                  type: 'success'
                }, function() {
                  loadPlans(currentType);
                });
              },
              error: function(xhr) {
                let errorMsg = 'Failed to delete plan.';
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                  errorMsg = xhr.responseJSON.errors.join(' ');
                }
                swal('Error', errorMsg, 'error');
              }
            });
          }
        });
      });
    });
  </script>

  <!-- Manage Categories Modal -->
  <div class="modal fade" id="manageCategoriesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h4 class="modal-title">Manage Plan Categories</h4>
        </div>
        <div class="modal-body">
          <p class="text-muted">Create and manage categories for your hosting plans. Each category needs a display name and a unique slug (e.g., "web" for "Web Hosting").</p>
          <div id="categoriesList">
            <!-- Categories will be loaded here -->
          </div>
          <button type="button" class="btn btn-sm btn-success" id="addCategoryButton" style="margin-top: 10px;">
            <i class="fa fa-plus"></i> Add Category
          </button>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="saveCategoriesButton">Save Categories</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let categoryTemplate = '<div class="category-item" style="margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">' +
      '<div class="row">' +
      '<div class="col-md-5">' +
      '<label>Display Name</label>' +
      '<input type="text" class="form-control category-name" placeholder="e.g., Game, VPS, Web" />' +
      '</div>' +
      '<div class="col-md-5">' +
      '<label>Slug</label>' +
      '<input type="text" class="form-control category-slug" placeholder="e.g., game-server, vps, web" />' +
      '</div>' +
      '<div class="col-md-2">' +
      '<label>&nbsp;</label><br />' +
      '<button type="button" class="btn btn-sm btn-danger remove-category"><i class="fa fa-trash"></i></button>' +
      '</div>' +
      '</div>' +
      '</div>';

    $(document).ready(function() {
      // Category button switching
      $(document).on('click', '.category-btn', function() {
        currentType = $(this).data('type');
        $('.category-btn').removeClass('btn-primary').addClass('btn-default');
        $(this).removeClass('btn-default').addClass('btn-primary');
        loadPlans(currentType);
      });

      // Open manage categories modal
      $('#manageCategoriesButton').on('click', function() {
        renderCategoriesList();
        $('#manageCategoriesModal').modal('show');
      });

      // Add new category
      $('#addCategoryButton').on('click', function() {
        $('#categoriesList').append(categoryTemplate);
      });

      // Remove category
      $(document).on('click', '.remove-category', function() {
        if (categories.length <= 1) {
          swal('Error', 'You must have at least one category.', 'error');
          return;
        }
        $(this).closest('.category-item').remove();
      });

      // Save categories
      $('#saveCategoriesButton').on('click', function() {
        const newCategories = [];
        $('.category-item').each(function() {
          const name = $(this).find('.category-name').val().trim();
          const slug = $(this).find('.category-slug').val().trim().toLowerCase().replace(/[^a-z0-9-]/g, '-');
          
          if (name && slug) {
            newCategories.push({ name: name, slug: slug });
          }
        });

        if (newCategories.length === 0) {
          swal('Error', 'You must have at least one category.', 'error');
          return;
        }

        // Check for duplicate slugs
        const slugs = newCategories.map(c => c.slug);
        if (new Set(slugs).size !== slugs.length) {
          swal('Error', 'Each category must have a unique slug.', 'error');
          return;
        }

        $('#saveCategoriesButton').prop('disabled', true).text('Saving...');

        $.ajax({
          url: '/admin/billing/plans/categories',
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({ categories: newCategories }),
          headers: {
            'X-CSRF-Token': $('meta[name="_token"]').attr('content')
          },
          success: function(response) {
            categories = response.data;
            renderCategoryButtons();
            $('#manageCategoriesModal').modal('hide');
            swal('Success', 'Categories updated successfully.', 'success');
          },
          error: function(xhr) {
            let errorMsg = 'Failed to save categories.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
              errorMsg = xhr.responseJSON.errors.join(' ');
            }
            swal('Error', errorMsg, 'error');
          },
          complete: function() {
            $('#saveCategoriesButton').prop('disabled', false).text('Save Categories');
          }
        });
      });
    });
  </script>

  <!-- Manage Billing Discounts Modal -->
  <div class="modal fade" id="manageBillingDiscountsModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
          <h4 class="modal-title">Manage Billing Period Discounts</h4>
        </div>
        <div class="modal-body">
          <p class="text-muted">Set discount percentages for each billing period per category. These discounts apply when users select different billing cycles.</p>
          <div id="billingDiscountsList">
            <!-- Discounts will be loaded here -->
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" id="saveBillingDiscountsButton">Save Discounts</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    function loadBillingDiscounts() {
      $.ajax({
        url: '/admin/billing/plans/billing-discounts',
        method: 'GET',
        success: function(response) {
          const discounts = response.data || {};
          renderBillingDiscounts(discounts);
        },
        error: function() {
          // Default discounts
          const defaultDiscounts = {};
          categories.forEach(function(cat) {
            defaultDiscounts[cat.slug] = {
              month: 0,
              quarter: 5,
              'half-year': 10,
              year: 20
            };
          });
          renderBillingDiscounts(defaultDiscounts);
        }
      });
    }

    function renderBillingDiscounts(discounts) {
      let html = '';
      categories.forEach(function(cat) {
        const catDiscounts = discounts[cat.slug] || { month: 0, quarter: 5, 'half-year': 10, year: 20 };
        html += '<div class="category-discounts" style="margin-bottom: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 4px;">';
        html += '<h4 style="margin-top: 0;">' + cat.name + ' Category</h4>';
        html += '<div class="row">';
        html += '<div class="col-md-3"><label>Monthly</label><input type="number" step="0.01" min="0" max="100" class="form-control discount-month" data-category="' + cat.slug + '" value="' + (catDiscounts.month || 0) + '" placeholder="0" /></div>';
        html += '<div class="col-md-3"><label>Quarterly</label><input type="number" step="0.01" min="0" max="100" class="form-control discount-quarter" data-category="' + cat.slug + '" value="' + (catDiscounts.quarter || 0) + '" placeholder="5" /></div>';
        html += '<div class="col-md-3"><label>Bi-Annual</label><input type="number" step="0.01" min="0" max="100" class="form-control discount-half-year" data-category="' + cat.slug + '" value="' + (catDiscounts['half-year'] || 0) + '" placeholder="10" /></div>';
        html += '<div class="col-md-3"><label>Yearly</label><input type="number" step="0.01" min="0" max="100" class="form-control discount-year" data-category="' + cat.slug + '" value="' + (catDiscounts.year || 0) + '" placeholder="20" /></div>';
        html += '</div>';
        html += '</div>';
      });
      $('#billingDiscountsList').html(html);
    }

    $(document).ready(function() {
      // Open manage billing discounts modal
      $('#manageBillingDiscountsButton').on('click', function() {
        loadBillingDiscounts();
        $('#manageBillingDiscountsModal').modal('show');
      });

      // Save billing discounts
      $('#saveBillingDiscountsButton').on('click', function() {
        const discounts = {};
        categories.forEach(function(cat) {
          discounts[cat.slug] = {
            month: parseFloat($('.discount-month[data-category="' + cat.slug + '"]').val() || 0),
            quarter: parseFloat($('.discount-quarter[data-category="' + cat.slug + '"]').val() || 0),
            'half-year': parseFloat($('.discount-half-year[data-category="' + cat.slug + '"]').val() || 0),
            year: parseFloat($('.discount-year[data-category="' + cat.slug + '"]').val() || 0)
          };
        });

        $('#saveBillingDiscountsButton').prop('disabled', true).text('Saving...');

        $.ajax({
          url: '/admin/billing/plans/billing-discounts',
          method: 'POST',
          contentType: 'application/json',
          data: JSON.stringify({ discounts: discounts }),
          headers: {
            'X-CSRF-Token': $('meta[name="_token"]').attr('content')
          },
          success: function(response) {
            $('#manageBillingDiscountsModal').modal('hide');
            swal('Success', 'Billing discounts updated successfully.', 'success');
          },
          error: function(xhr) {
            let errorMsg = 'Failed to save discounts.';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
              errorMsg = xhr.responseJSON.errors.join(' ');
            }
            swal('Error', errorMsg, 'error');
          },
          complete: function() {
            $('#saveBillingDiscountsButton').prop('disabled', false).text('Save Discounts');
          }
        });
      });
    });
  </script>
@endsection
