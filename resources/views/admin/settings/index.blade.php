@extends('layouts.admin')
@include('partials/admin.settings.nav', ['activeTab' => 'basic'])

@section('title')
  Settings
@endsection

@section('content-header')
  <h1>Panel Settings<small>Configure Pterodactyl to your liking.</small></h1>
  <ol class="breadcrumb">
    <li><a href="{{ route('admin.index') }}">Admin</a></li>
    <li class="active">Settings</li>
  </ol>
@endsection

@section('content')
  @yield('settings::nav')
  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Panel Settings</h3>
        </div>
        <form action="{{ route('admin.settings') }}" method="POST">
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-4">
                <label class="control-label">Company Name</label>
                <div>
                  <input type="text" class="form-control" name="app:name"
                    value="{{ old('app:name', config('app.name')) }}" />
                  <p class="text-muted"><small>This is the name that is used throughout the panel and in emails sent to
                      clients.</small></p>
                </div>
              </div>
              <div class="form-group col-md-4">
                <label class="control-label">Require 2-Factor Authentication</label>
                <div>
                  <div class="btn-group" data-toggle="buttons">
                    @php
                      $level = old('pterodactyl:auth:2fa_required', config('pterodactyl.auth.2fa_required'));
                    @endphp
                    <label class="btn btn-outline-primary @if ($level == 0) active @endif">
                      <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="0" @if ($level == 0) checked @endif> Not Required
                    </label>
                    <label class="btn btn-outline-primary @if ($level == 1) active @endif">
                      <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="1" @if ($level == 1) checked @endif> Admin Only
                    </label>
                    <label class="btn btn-outline-primary @if ($level == 2) active @endif">
                      <input type="radio" name="pterodactyl:auth:2fa_required" autocomplete="off" value="2" @if ($level == 2) checked @endif> All Users
                    </label>
                  </div>
                  <p class="text-muted"><small>If enabled, any account falling into the selected grouping will be required
                      to have 2-Factor authentication enabled to use the Panel.</small></p>
                </div>
              </div>
              <div class="form-group col-md-4">
                <label class="control-label">Default Language</label>
                <div>
                  <select name="app:locale" class="form-control">
                    @foreach($languages as $key => $value)
                      <option value="{{ $key }}" @if(config('app.locale') === $key) selected @endif>{{ $value }}</option>
                    @endforeach
                  </select>
                  <p class="text-muted"><small>The default language to use when rendering UI components.</small></p>
                </div>
              </div>
            </div>
          </div>
          <div class="box-footer">
            {!! csrf_field() !!}
            <button type="submit" name="_method" value="PATCH"
              class="btn btn-primary btn-sm btn-outline-primary pull-right">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="row">
    <div class="col-xs-12">
      <div class="box">
        <div class="box-header with-border">
          <h3 class="box-title">Proxmox Settings</h3>
        </div>
        <form action="{{ route('admin.settings') }}" method="POST">
          <div class="box-body">
            <div class="row">
              <div class="form-group col-md-6">
                <label class="control-label">Proxmox API URL</label>
                <div>
                  <input type="url" class="form-control" name="proxmox:url"
                    value="{{ old('proxmox:url', config('proxmox.url')) }}"
                    placeholder="https://proxmox.example.com:8006" />
                  <p class="text-muted"><small>The full URL to your Proxmox API endpoint (including port, typically 8006).</small></p>
                </div>
              </div>
              <div class="form-group col-md-6">
                <label class="control-label">Proxmox API Token</label>
                <div>
                  <input type="text" class="form-control" name="proxmox:api_token"
                    value="{{ old('proxmox:api_token', config('proxmox.api_token')) }}"
                    placeholder="user@realm!tokenid=secret" />
                  <p class="text-muted"><small>Proxmox API token in format: user@realm!tokenid=secret</small></p>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-4">
                <label class="control-label">Authentication Realm</label>
                <div>
                  <input type="text" class="form-control" name="proxmox:realm"
                    value="{{ old('proxmox:realm', config('proxmox.realm', 'pam')) }}"
                    placeholder="pam" />
                  <p class="text-muted"><small>Proxmox authentication realm (default: pam).</small></p>
                </div>
              </div>
              <div class="form-group col-md-4">
                <label class="control-label">Default Node Name</label>
                <div>
                  <input type="text" class="form-control" name="proxmox:node"
                    value="{{ old('proxmox:node', config('proxmox.node')) }}"
                    placeholder="pve" />
                  <p class="text-muted"><small>The default Proxmox node name where VPS servers will be created.</small></p>
                </div>
              </div>
              <div class="form-group col-md-4">
                <label class="control-label">Default Storage Pool</label>
                <div>
                  <input type="text" class="form-control" name="proxmox:storage"
                    value="{{ old('proxmox:storage', config('proxmox.storage')) }}"
                    placeholder="local-lvm" />
                  <p class="text-muted"><small>The default storage pool name for VPS server disks.</small></p>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="form-group col-md-12">
                <label class="control-label">Ubuntu Server Template</label>
                <div>
                  <input type="text" class="form-control" name="proxmox:template"
                    value="{{ old('proxmox:template', config('proxmox.template')) }}"
                    placeholder="local:vztmpl/ubuntu-22.04-cloudinit-template.tar.gz" />
                  <p class="text-muted"><small>Template ID or path for Ubuntu Server (e.g., storage:template-name or template-id).</small></p>
                </div>
              </div>
            </div>
          </div>
          <div class="box-footer">
            {!! csrf_field() !!}
            <button type="submit" name="_method" value="PATCH"
              class="btn btn-primary btn-sm btn-outline-primary pull-right">Save</button>
          </div>
        </form>
      </div>
    </div>
  </div>
@endsection
