<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Services\BlogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AdminBlogCategoryController extends Controller
{
    public function __construct(protected BlogService $blog)
    {
    }

    public function index()
    {
        return view('admin.blog.categories', [
            'categories' => BlogCategory::withCount('posts')
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = BlogCategory::uniqueSlug($data['slug'] ?: $data['name']);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            $data['image'] = $this->blog->storeUpload($request->file('image'), $request->user()->id, 'manual', 'blog/categories')->path;
        }

        BlogCategory::create($data);

        return back()->with('success', 'Category created.');
    }

    public function update(Request $request, BlogCategory $category)
    {
        $data = $this->validated($request);
        $data['slug'] = BlogCategory::uniqueSlug($data['slug'] ?: $data['name'], $category->id);
        $data['is_active'] = $request->boolean('is_active', true);

        if ($request->hasFile('image')) {
            if ($category->image && ! str_starts_with($category->image, 'http')) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $this->blog->storeUpload($request->file('image'), $request->user()->id, 'manual', 'blog/categories')->path;
        }

        $category->update($data);

        return back()->with('success', 'Category updated.');
    }

    public function destroy(BlogCategory $category)
    {
        // Posts survive; they simply lose their category.
        $category->posts()->update(['blog_category_id' => null]);
        $category->delete();

        return back()->with('success', 'Category deleted.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:120',
            'slug' => 'nullable|string|max:140',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:60',
            'color' => 'nullable|string|max:20',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:320',
            'sort_order' => 'nullable|integer|min:0',
            'image' => 'nullable|image|max:4096|mimes:jpg,jpeg,png,webp',
        ]);
    }
}
