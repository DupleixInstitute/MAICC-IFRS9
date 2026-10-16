@extends('errors.layout')

@section('title', 'Too many requests')
@section('code', '429')
@section('message', 'Too many requests in a short time. Wait a moment and try again.')
