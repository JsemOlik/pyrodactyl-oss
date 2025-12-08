<form id="billingSettingsForm">
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
        <div class="box-footer">
          {{ csrf_field() }}
          <div class="pull-right">
            <button type="button" id="saveButton" class="btn btn-sm btn-primary">Save Settings</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
