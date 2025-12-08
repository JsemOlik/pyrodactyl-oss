<script>
  let currentPage = 1;
  let currentSearch = '';
  let selectedUserId = null;

  function loadUsers(page = 1, search = '') {
    $.ajax({
      url: '/admin/billing/credits/users',
      method: 'GET',
      data: {
        page: page,
        per_page: 25,
        search: search
      },
      success: function(response) {
        const users = response.data;
        const pagination = response.meta.pagination;
        
        let html = '';
        if (users.length === 0) {
          html = '<tr><td colspan="6" class="text-center">No users found.</td></tr>';
        } else {
          users.forEach(function(user) {
            const name = (user.name_first || '') + ' ' + (user.name_last || '');
            html += '<tr>';
            html += '<td>' + user.id + '</td>';
            html += '<td>' + (user.username || 'N/A') + '</td>';
            html += '<td>' + (user.email || 'N/A') + '</td>';
            html += '<td>' + (name.trim() || 'N/A') + '</td>';
            html += '<td><strong>' + parseFloat(user.credits_balance || 0).toFixed(2) + ' credits</strong></td>';
            html += '<td>';
            html += '<button class="btn btn-xs btn-info viewTransactions" data-user-id="' + user.id + '" data-username="' + (user.username || 'User #' + user.id) + '">';
            html += '<i class="fa fa-history"></i> Transactions';
            html += '</button> ';
            html += '<button class="btn btn-xs btn-warning adjustCredits" data-user-id="' + user.id + '" data-username="' + (user.username || 'User #' + user.id) + '" data-balance="' + (user.credits_balance || 0) + '">';
            html += '<i class="fa fa-edit"></i> Adjust';
            html += '</button>';
            html += '</td>';
            html += '</tr>';
          });
        }
        
        $('#usersTableBody').html(html);
        
        // Update pagination
        let paginationHtml = '';
        if (pagination.total_pages > 1) {
          paginationHtml += '<ul class="pagination">';
          
          // Previous button
          if (pagination.current_page > 1) {
            paginationHtml += '<li><a href="#" class="page-link" data-page="' + (pagination.current_page - 1) + '">«</a></li>';
          }
          
          // Page numbers
          for (let i = 1; i <= pagination.total_pages; i++) {
            if (i === pagination.current_page) {
              paginationHtml += '<li class="active"><span>' + i + '</span></li>';
            } else {
              paginationHtml += '<li><a href="#" class="page-link" data-page="' + i + '">' + i + '</a></li>';
            }
          }
          
          // Next button
          if (pagination.current_page < pagination.total_pages) {
            paginationHtml += '<li><a href="#" class="page-link" data-page="' + (pagination.current_page + 1) + '">»</a></li>';
          }
          
          paginationHtml += '</ul>';
        }
        $('#paginationContainer').html(paginationHtml);
        
        currentPage = pagination.current_page;
      },
      error: function(xhr) {
        $('#usersTableBody').html('<tr><td colspan="6" class="text-center text-red">Failed to load users.</td></tr>');
      }
    });
  }

  function loadUserTransactions(userId) {
    $.ajax({
      url: '/admin/billing/credits/users/' + userId + '/transactions',
      method: 'GET',
      success: function(response) {
        const transactions = response.data;
        let html = '<h4>Transaction History</h4>';
        html += '<div class="table-responsive">';
        html += '<table class="table table-striped table-bordered">';
        html += '<thead><tr><th>Date</th><th>Type</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Description</th></tr></thead>';
        html += '<tbody>';
        
        if (transactions.length === 0) {
          html += '<tr><td colspan="6" class="text-center">No transactions found.</td></tr>';
        } else {
          transactions.forEach(function(tx) {
            const date = new Date(tx.created_at).toLocaleString();
            const typeColors = {
              'purchase': 'label-success',
              'deduction': 'label-danger',
              'refund': 'label-info',
              'renewal': 'label-warning',
              'adjustment': 'label-primary'
            };
            const typeColor = typeColors[tx.type] || 'label-default';
            const amountSign = (tx.type === 'purchase' || tx.type === 'refund') ? '+' : '-';
            const amountColor = (tx.type === 'purchase' || tx.type === 'refund') ? 'text-green' : 'text-red';
            
            html += '<tr>';
            html += '<td>' + date + '</td>';
            html += '<td><span class="label ' + typeColor + '">' + tx.type.toUpperCase() + '</span></td>';
            html += '<td class="' + amountColor + '">' + amountSign + parseFloat(tx.amount).toFixed(2) + '</td>';
            html += '<td>' + parseFloat(tx.balance_before).toFixed(2) + '</td>';
            html += '<td><strong>' + parseFloat(tx.balance_after).toFixed(2) + '</strong></td>';
            html += '<td>' + (tx.description || '—') + '</td>';
            html += '</tr>';
          });
        }
        
        html += '</tbody></table></div>';
        $('#userDetailsContent').html(html);
      },
      error: function(xhr) {
        $('#userDetailsContent').html('<div class="alert alert-danger">Failed to load transactions.</div>');
      }
    });
  }

  $(document).ready(function() {
    // Load initial users
    loadUsers();

    // Search functionality
    $('#searchButton').on('click', function() {
      currentSearch = $('#userSearchInput').val();
      currentPage = 1;
      loadUsers(currentPage, currentSearch);
    });

    $('#userSearchInput').on('keypress', function(e) {
      if (e.which === 13) {
        $('#searchButton').click();
      }
    });

    // Pagination
    $(document).on('click', '.page-link', function(e) {
      e.preventDefault();
      const page = $(this).data('page');
      if (page) {
        loadUsers(page, currentSearch);
      }
    });

    // View transactions
    $(document).on('click', '.viewTransactions', function() {
      const userId = $(this).data('user-id');
      const username = $(this).data('username');
      selectedUserId = userId;
      
      $('#userDetailsModal .modal-title').text('Credits Details - ' + username);
      $('#userDetailsContent').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</div>');
      $('#userDetailsModal').modal('show');
      
      loadUserTransactions(userId);
    });

    // Adjust credits
    $(document).on('click', '.adjustCredits', function() {
      const userId = $(this).data('user-id');
      const username = $(this).data('username');
      const balance = parseFloat($(this).data('balance'));
      
      selectedUserId = userId;
      $('#adjustUserId').val(userId);
      $('#adjustUserInfo').val(username + ' (ID: ' + userId + ')');
      $('#adjustCurrentBalance').val(balance.toFixed(2) + ' credits');
      $('#adjustAmount').val('');
      $('#adjustDescription').val('');
      $('#adjustNewBalance').val(balance.toFixed(2) + ' credits');
      
      $('#adjustCreditsModal').modal('show');
    });

    // Calculate new balance on amount change
    $('#adjustAmount').on('input', function() {
      const currentBalance = parseFloat($('#adjustCurrentBalance').val().replace(' credits', ''));
      const amount = parseFloat($(this).val()) || 0;
      const newBalance = currentBalance + amount;
      $('#adjustNewBalance').val(newBalance.toFixed(2) + ' credits');
    });

    // Submit adjustment
    $('#adjustCreditsForm').on('submit', function(e) {
      e.preventDefault();
      
      const userId = $('#adjustUserId').val();
      const amount = parseFloat($('#adjustAmount').val());
      const description = $('#adjustDescription').val();
      
      if (!description.trim()) {
        swal('Error', 'Please enter a description.', 'error');
        return;
      }
      
      $('#adjustSubmitButton').prop('disabled', true).text('Processing...');
      
      $.ajax({
        url: '/admin/billing/credits/users/' + userId + '/adjust',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify({
          amount: amount,
          description: description
        }),
        headers: {
          'X-CSRF-Token': $('input[name="_token"]').val() || $('meta[name="_token"]').attr('content')
        },
        success: function(response) {
          swal({
            title: 'Success',
            text: 'Credits adjusted successfully. New balance: ' + response.data.new_balance.toFixed(2) + ' credits',
            type: 'success'
          }, function() {
            $('#adjustCreditsModal').modal('hide');
            loadUsers(currentPage, currentSearch);
          });
        },
        error: function(xhr) {
          let errorMsg = 'Failed to adjust credits.';
          if (xhr.responseJSON && xhr.responseJSON.errors) {
            errorMsg = xhr.responseJSON.errors[0].detail || errorMsg;
          }
          swal('Error', errorMsg, 'error');
        },
        complete: function() {
          $('#adjustSubmitButton').prop('disabled', false).text('Apply Adjustment');
        }
      });
    });
  });
</script>
