<form id="billingSettingsForm">
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Server Creation Control</h3>
        </div>
        <div class="box-body">
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $enableServerCreation = old('billing:enable_server_creation', config('billing.enable_server_creation', true));
                  $enableServerCreationValue = is_bool($enableServerCreation) ? $enableServerCreation : ($enableServerCreation === 'true' || $enableServerCreation === true || $enableServerCreation === '1');
                @endphp
                <input id="billingEnableServerCreation" type="checkbox" name="billing:enable_server_creation" value="1"
                  {{ $enableServerCreationValue ? 'checked' : '' }} />
                <label for="billingEnableServerCreation">Enable server creation through billing process</label>
              </div>
              <p class="text-muted small">When disabled, customers will be unable to create new servers through the billing process. Admins can still create servers manually through the admin dashboard.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <label class="control-label">Disabled Message <span class="field-optional"></span></label>
              <div>
                <textarea class="form-control" name="billing:server_creation_disabled_message" rows="4"
                  placeholder="We're currently scaling our infrastructure to provide better service. Server creation is temporarily disabled. Please check back soon!">{{ old('billing:server_creation_disabled_message', config('billing.server_creation_disabled_message', '')) }}</textarea>
                <p class="text-muted small">This message will be displayed to users when server creation is disabled.</p>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-6">
              <label class="control-label">Status Page URL <span class="field-optional"></span></label>
              <div>
                <input type="url" class="form-control" name="billing:status_page_url"
                  value="{{ old('billing:status_page_url', config('billing.status_page_url', '')) }}" placeholder="https://status.example.com" />
                <p class="text-muted small">The URL to your status page. Users will see a button linking to this page when server creation is disabled.</p>
              </div>
            </div>
            <div class="form-group col-md-6">
              <div class="checkbox checkbox-primary" style="margin-top: 25px;">
                @php
                  $showStatusButton = old('billing:show_status_page_button', config('billing.show_status_page_button', false));
                  $showStatusButtonValue = is_bool($showStatusButton) ? $showStatusButton : ($showStatusButton === 'true' || $showStatusButton === true || $showStatusButton === '1');
                @endphp
                <input id="billingShowStatusPageButton" type="checkbox" name="billing:show_status_page_button" value="1"
                  {{ $showStatusButtonValue ? 'checked' : '' }} />
                <label for="billingShowStatusPageButton">Show status page button</label>
              </div>
              <p class="text-muted small">Display a "View our status page" button on the server creation disabled page.</p>
            </div>
          </div>
          <div class="row">
            <div class="form-group col-md-12">
              <div class="checkbox checkbox-primary">
                @php
                  $showLogo = old('billing:show_logo_on_disabled_page', config('billing.show_logo_on_disabled_page', true));
                  $showLogoValue = is_bool($showLogo) ? $showLogo : ($showLogo === 'true' || $showLogo === true || $showLogo === '1');
                @endphp
                <input id="billingShowLogoOnDisabledPage" type="checkbox" name="billing:show_logo_on_disabled_page" value="1"
                  {{ $showLogoValue ? 'checked' : '' }} />
                <label for="billingShowLogoOnDisabledPage">Show logo on server creation disabled page</label>
              </div>
              <p class="text-muted small">Display the logo on the server creation disabled page. When disabled, only the message and buttons will be shown.</p>
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
