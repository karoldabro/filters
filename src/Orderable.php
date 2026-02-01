<?php

namespace Kdabrow\Filters;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
interface Orderable
{
	/**
	 * @return array<string, array{allowedDirections: string[], column?: string, callback?: callable|string}>
	 */
	public function orders(): array;
}