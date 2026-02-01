<?php

namespace Kdabrow\Filters;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
interface Filterable
{
	/**
	 * @return array<string, array{allowedOperators: string[], column?: string, callback?: callable|string}>
	 */
	public function filters(): array;
}