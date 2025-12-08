<form id="billingSettingsForm">
  {{ csrf_field() }}
  
  <!-- Payment Method -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Payment Method</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $enableCredits = old('billing:enable_credits', config('billing.enable_credits', false));
                  $enableCreditsValue = is_bool($enableCredits) ? $enableCredits : ($enableCredits === 'true' || $enableCredits === true || $enableCredits === '1');
                @endphp
                <input id="billingEnableCredits" type="checkbox" name="billing:enable_credits" value="1"
                  {{ $enableCreditsValue ? 'checked' : '' }} />
                <label for="billingEnableCredits">Enable Credits System</label>
              </div>
              <p class="text-muted small">When enabled, users must purchase credits to buy servers. Users can buy credits from their billing dashboard. When disabled, users pay directly with their card at checkout.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Credits System Settings -->
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Credits System Settings</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Credit Conversion Rate <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0.01" class="form-control" name="billing:credit_conversion_rate"
                  value="{{ old('billing:credit_conversion_rate', config('billing.credit_conversion_rate', 1)) }}" placeholder="1.00" />
                <p class="text-muted small">How many credits equal 1 unit of currency (e.g., 1.00 = 1 credit = $1.00).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Minimum Credit Purchase <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" class="form-control" name="billing:min_credit_purchase"
                  value="{{ old('billing:min_credit_purchase', config('billing.min_credit_purchase', 0)) }}" placeholder="0.00" />
                <p class="text-muted small">Minimum amount of credits a user must purchase at once.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Maximum Credit Balance <span class="field-optional"></span></label>
              <div>
                <input type="number" step="0.01" min="0" class="form-control" name="billing:max_credit_balance"
                  value="{{ old('billing:max_credit_balance', config('billing.max_credit_balance', 0)) }}" placeholder="0.00 (0 = unlimited)" />
                <p class="text-muted small">Maximum credit balance a user can have (0 = unlimited).</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <label class="control-label">Credit Expiration Days <span class="field-optional"></span></label>
              <div>
                <input type="number" min="0" class="form-control" name="billing:credit_expiration_days"
                  value="{{ old('billing:credit_expiration_days', config('billing.credit_expiration_days', 0)) }}" placeholder="0 (0 = never expire)" />
                <p class="text-muted small">Number of days before credits expire (0 = never expire).</p>
              </div>
            </div>
          </div>
        </div>
        <div class="box-footer">
          <div class="pull-right">
            <button type="button" id="saveCreditsSettingsButton" class="btn btn-sm btn-primary">Save Credits Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>

<div class="row">
  <div class="col-xs-12">
    <div class="box">
      <div class="box-header with-border">
        <h3 class="box-title">User Credits Management</h3>
        <div class="box-tools pull-right">
          <div class="input-group" style="width: 250px;">
            <input type="text" id="userSearchInput" class="form-control" placeholder="Search users...">
            <div class="input-group-btn">
              <button type="button" id="searchButton" class="btn btn-default">
                <i class="fa fa-search"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <div class="box-body">
        <div class="table-responsive">
          <table class="table table-hover" id="usersTable">
            <thead>
              <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Email</th>
                <th>Name</th>
                <th>Credits Balance</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="usersTableBody">
              <tr>
                <td colspan="6" class="text-center">Loading users...</td>
              </tr>
            </tbody>
          </table>
        </div>
        <div id="paginationContainer" class="text-center"></div>
      </div>
    </div>
  </div>
</div>

<!-- User Details Modal -->
<div class="modal fade" id="userDetailsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title">User Credits Details</h4>
      </div>
      <div class="modal-body">
        <div id="userDetailsContent">
          <div class="text-center">
            <i class="fa fa-spinner fa-spin"></i> Loading...
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Adjust Credits Modal -->
<div class="modal fade" id="adjustCreditsModal" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 class="modal-title">Adjust User Credits</h4>
      </div>
      <form id="adjustCreditsForm">
        <div class="modal-body">
          <div class="form-group">
            <label>User</label>
            <input type="text" id="adjustUserInfo" class="form-control" readonly>
            <input type="hidden" id="adjustUserId">
          </div>
          <div class="form-group">
            <label>Current Balance</label>
            <input type="text" id="adjustCurrentBalance" class="form-control" readonly>
          </div>
          <div class="form-group">
            <label>Amount <span class="text-red">*</span></label>
            <input type="number" step="0.01" id="adjustAmount" class="form-control" required>
            <p class="help-block">Enter positive number to add credits, negative to deduct.</p>
          </div>
          <div class="form-group">
            <label>Description <span class="text-red">*</span></label>
            <input type="text" id="adjustDescription" class="form-control" required placeholder="e.g., Manual adjustment by admin">
          </div>
          <div class="form-group">
            <label>New Balance (Preview)</label>
            <input type="text" id="adjustNewBalance" class="form-control" readonly>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-primary" id="adjustSubmitButton">Apply Adjustment</button>
        </div>
      </form>
    </div>
  </div>
</div>
