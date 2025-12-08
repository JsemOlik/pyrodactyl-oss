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
          <table class="table table-striped table-bordered" id="usersTable">
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
