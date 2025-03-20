@extends('adminlte::page')
@section('title', 'Kehadiran Pegawai I')
@section('content_header')
    <h1 class="m-0 text-dark"></h1>
@stop
@section('content')
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h3>Rekapitulasi Kehadiran Pegawai I</h3>
                    <div class="lead">
                        
                    </div>

                    <div class="mt-2">
                        @include('layouts.partials.messages')
                    </div>

                    <table class="table table-bordered">
                        
                    </table>

                </div>
            </div>
        </div>
    </div>
@stop