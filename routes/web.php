<?php
use App\Http\Controllers\ScenariosController;
use App\Http\Controllers\MacroForecastWeightedController;
use App\Http\Controllers\MacroStatsController;
use App\Http\Controllers\MacroStatsValueController;
use App\Http\Controllers\ManualsController;
use App\Http\Controllers\LossGiveDefaultController;
use App\Http\Controllers\LossGivenDefaultCummulativeController;
use App\Http\Controllers\ExpectedCreditLossController;
use Inertia\Inertia;
use App\Http\Controllers\ImportsController;
use Illuminate\Support\Facades\Artisan;
use Webit\Util\EvalMath\EvalMath;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BanksController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\FormsController;
use App\Http\Controllers\RolesController;
use App\Http\Controllers\UsersController;
use App\Http\Controllers\WardsController;
use App\Http\Controllers\ClientsController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\CoursesController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\RegionsController;
use App\Http\Controllers\ReportsController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\BranchesController;
use App\Http\Controllers\LoanBookController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\VillagesController;
use App\Http\Controllers\ContractsController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DistrictsController;
use App\Http\Controllers\InkhundlaController;
use App\Http\Controllers\LoanFilesController;
use App\Http\Controllers\LoanNotesController;
use App\Http\Controllers\ProvincesController;
use App\Http\Controllers\CurrenciesController;
use App\Http\Controllers\LegalTypesController;
use App\Http\Controllers\ClientFilesController;
use App\Http\Controllers\ClientNotesController;
use App\Http\Controllers\ExcelExportController;
use App\Http\Controllers\ExpenseTypeController;
use App\Http\Controllers\PaymentTypeController;
use App\Http\Controllers\SmsGatewaysController;
use App\Http\Controllers\ClientImportController;
use App\Http\Controllers\ClientIncomeController;
use App\Http\Controllers\ClientPotersController;
use App\Http\Controllers\JournalEntryController;
use App\Http\Controllers\LoanProductsController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\IndustryTypesController;
use  App\Http\Controllers\GeneralImportController;
use App\Http\Controllers\ChartOfAccountController;
use App\Http\Controllers\LoanGuarantorsController;
use App\Http\Controllers\TransitionProfileDefinitionController;
use App\Http\Controllers\TransitionProfileOptionController;
use App\Http\Controllers\TransitionMatrixCummulativeController;

// use App\Http\Controllers\LoanApplicationFilesController;

use App\Http\Controllers\LoanPortfoliosController;
use App\Http\Controllers\CourseMaterialsController;
use App\Http\Controllers\EventCategoriesController;
use App\Http\Controllers\FinancialPeriodController;
use App\Http\Controllers\CommunicationLogController;
use App\Http\Controllers\CourseCategoriesController;
use App\Http\Controllers\LoanApplicationsController;
use App\Http\Controllers\TransitionMatrixController;
use App\Http\Controllers\ArticleCategoriesController;
use App\Http\Controllers\ScoringAttributesController;
use App\Http\Controllers\ClientBalanceSheetController;
use App\Http\Controllers\ClientLoginDetailsController;
use App\Http\Controllers\ClientShareholdersController;
use App\Http\Controllers\LoanApprovalStagesController;
use App\Http\Controllers\ClientRatioAnalysisController;
use App\Http\Controllers\CourseRegistrationsController;
use App\Http\Controllers\CommunicationCampaignController;
use App\Http\Controllers\CommunicationTemplateController;
use App\Http\Controllers\LoanProductCategoriesController;
use App\Http\Controllers\MemberPortal\MemberPortalLoansController;
use App\Http\Controllers\MemberPortal\MemberPortalUsersController;
use App\Http\Controllers\MemberPortal\MemberPortalEventsController;
use App\Http\Controllers\MemberPortal\MemberPortalCoursesController;
use App\Http\Controllers\MemberPortal\MemberPortalArticlesController;
use App\Http\Controllers\MemberPortal\MemberPortalLoanFilesController;
use App\Http\Controllers\MemberPortal\MemberPortalLoanNotesController;
use App\Http\Controllers\MemberPortal\MemberPortalLoanGuarantorsController;
use App\Http\Controllers\MemberPortal\MemberPortalCourseMaterialsController;
use App\Http\Controllers\MemberPortal\MemberPortalCourseRegistrationsController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
*/


Route::get('/', [DashboardController::class, 'index'])->name('home');
Route::post('/export/excel', [DashboardController::class, 'export'])->name('export.excel');
Route::group(['prefix' => 'dashboard'], function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('{scope}/create-filter', [DashboardController::class, 'filter'])->name('dashboard.filter');
    Route::get('filter-results', [DashboardController::class, 'filterResults'])->name('dashboard.filter-results');
    Route::get('my-workspace', [DashboardController::class, 'myWorkspace'])->name('dashboard.my-workspace');
});
//users
Route::group(['prefix' => 'user'], function () {
    Route::get('/', [UsersController::class, 'index'])->name('users.index');
    Route::get('/search', [UsersController::class, 'search'])->name('users.search');
    Route::get('/create', [UsersController::class, 'create'])->name('users.create');
    Route::post('/store', [UsersController::class, 'store'])->name('users.store');
    Route::get('/{user}/show', [UsersController::class, 'show'])->name('users.show');
    Route::get('/{user}/edit', [UsersController::class, 'edit'])->name('users.edit');
    Route::put('/{user}/update', [UsersController::class, 'update'])->name('users.update');
    Route::delete('/{user}/destroy', [UsersController::class, 'destroy'])->name('users.destroy');
    //manage roles
    Route::get('/role', [RolesController::class, 'index'])->name('users.roles.index');
    Route::get('/role/create', [RolesController::class, 'create'])->name('users.roles.create');
    Route::post('/role/store', [RolesController::class, 'store'])->name('users.roles.store');
    Route::get('/role/{role}/show', [RolesController::class, 'show'])->name('users.roles.show');
    Route::get('/role/{role}/edit', [RolesController::class, 'edit'])->name('users.roles.edit');
    Route::put('/role/{role}/update', [RolesController::class, 'update'])->name('users.roles.update');
    Route::delete('/role/{role}/destroy', [RolesController::class, 'destroy'])->name('users.roles.destroy');

});
//loan products
Route::prefix('loan_product')->name('loan_products.')->group(function () {
    Route::get('/', [LoanProductsController::class, 'index'])->name('index');
    Route::get('/create', [LoanProductsController::class, 'create'])->name('create');
    Route::post('/store', [LoanProductsController::class, 'store'])->name('store');
    Route::get('/{product}/show', [LoanProductsController::class, 'show'])->name('show');
    Route::get('/{product}/edit', [LoanProductsController::class, 'edit'])->name('edit');
    Route::put('/{product}/update', [LoanProductsController::class, 'update'])->name('update');
    Route::delete('/{product}/destroy', [LoanProductsController::class, 'destroy'])->name('destroy');
    Route::post('/{product}/sync_attributes', [LoanProductsController::class, 'syncAttributes'])->name('sync_attributes');
//comments
    Route::post('/{article}/comment/store', [LoanProductsController::class, 'storeComment'])->name('comments.store');
    Route::delete('/comment/{comment}/destroy', [LoanProductsController::class, 'destroyComment'])->name('comments.destroy');

    //categories
    Route::prefix('category')->name('categories.')->group(function () {
        Route::get('/', [LoanProductCategoriesController::class, 'index'])->name('index');
        Route::get('/create', [LoanProductCategoriesController::class, 'create'])->name('create');
        Route::post('/store', [LoanProductCategoriesController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [LoanProductCategoriesController::class, 'edit'])->name('edit');
        Route::put('/{category}/update', [LoanProductCategoriesController::class, 'update'])->name('update');
        Route::delete('/{category}/destroy', [LoanProductCategoriesController::class, 'destroy'])->name('destroy');
    });
});
//events
Route::prefix('scoring_attribute')->name('scoring_attributes.')->group(function () {
    Route::get('/', [ScoringAttributesController::class, 'index'])->name('index');
    Route::get('/create', [ScoringAttributesController::class, 'create'])->name('create');
    Route::post('/store', [ScoringAttributesController::class, 'store'])->name('store');
    Route::get('/{attribute}/show', [ScoringAttributesController::class, 'show'])->name('show');
    Route::get('/{attribute}/edit', [ScoringAttributesController::class, 'edit'])->name('edit');
    Route::put('/{attribute}/update', [ScoringAttributesController::class, 'update'])->name('update');
    Route::delete('/{attribute}/destroy', [ScoringAttributesController::class, 'destroy'])->name('destroy');
    Route::post('/{attribute}/items/store', [ScoringAttributesController::class, 'storeItem'])->name('items.store');
    Route::delete('/items/{attribute}/destroy', [ScoringAttributesController::class, 'destroyItem'])->name('items.destroy');
    Route::get('/table-columns', [ScoringAttributesController::class, 'getTableColumns'])->name('table-columns');


});

//clients
Route::group(['prefix' => 'client', 'as' => 'clients.'], function () {
    Route::get('/', [ClientsController::class, 'index'])->name('index');
    Route::get('/create', [ClientsController::class, 'create'])->name('create');
    Route::get('/search', [ClientsController::class, 'search'])->name('search');
    Route::post('/store', [ClientsController::class, 'store'])->name('store');
    Route::get('/{client}/show', [ClientsController::class, 'show'])->name('show');
    Route::get('/{client}/edit', [ClientsController::class, 'edit'])->name('edit');
    Route::put('/{client}/update', [ClientsController::class, 'update'])->name('update');
    Route::delete('/{client}/destroy', [ClientsController::class, 'destroy'])->name('destroy');
    #Route::post('/import', [ClientImportController::class, 'import'])->name('import');
    Route::get('/import', [ClientsController::class, 'createImport'])->name('import.create');
    Route::post('/import', [ClientsController::class, 'import'])->name('import.store');
    //loans
    Route::get('{client}/loan_application', [ClientsController::class, 'loanApplication'])->name('loan_applications.index');
    Route::get('{client}/course', [ClientsController::class, 'courses'])->name('courses.index');

    //login details
    Route::get('{client}/login_detail', [ClientLoginDetailsController::class, 'index'])->name('login_details.index');
    Route::get('{client}/login_detail/create', [ClientLoginDetailsController::class, 'create'])->name('login_details.create');
    Route::post('{client}/login_detail/store', [ClientLoginDetailsController::class, 'store'])->name('login_details.store');
    Route::delete('login_detail/{clientUser}/destroy', [ClientLoginDetailsController::class, 'destroy'])->name('login_details.destroy');
    //files
    Route::get('{client}/file', [ClientFilesController::class, 'index'])->name('files.index');
    Route::get('{client}/file/create', [ClientFilesController::class, 'create'])->name('files.create');
    Route::post('{client}/file/store', [ClientFilesController::class, 'store'])->name('files.store');
    Route::get('file/{file}/show', [ClientFilesController::class, 'show'])->name('files.show');
    Route::get('file/{file}/edit', [ClientFilesController::class, 'edit'])->name('files.edit');
    Route::put('file/{file}/update', [ClientFilesController::class, 'update'])->name('files.update');
    Route::delete('file/{file}/destroy', [ClientFilesController::class, 'destroy'])->name('files.destroy');
    //ratio analysis
    Route::get('{client}/ratio_analysis', [ClientRatioAnalysisController::class, 'index'])->name('ratio_analysis.index');
    Route::get('{client}/ratio_analysis/create', [ClientRatioAnalysisController::class, 'create'])->name('ratio_analysis.create');
    Route::post('{client}/ratio_analysis/store', [ClientRatioAnalysisController::class, 'store'])->name('ratio_analysis.store');
    Route::get('ratio_analysis/{ratio}/show', [ClientRatioAnalysisController::class, 'show'])->name('ratio_analysis.show');
    Route::get('ratio_analysis/{ratio}/edit', [ClientRatioAnalysisController::class, 'edit'])->name('ratio_analysis.edit');
    Route::put('ratio_analysis/{ratio}/update', [ClientRatioAnalysisController::class, 'update'])->name('ratio_analysis.update');
    Route::delete('ratio_analysis/{ratio}/destroy', [ClientRatioAnalysisController::class, 'destroy'])->name('ratio_analysis.destroy');
    //notes
    Route::get('{client}/note', [ClientNotesController::class, 'index'])->name('notes.index');
    Route::get('{client}/note/create', [ClientNotesController::class, 'create'])->name('notes.create');
    Route::post('{client}/note/store', [ClientNotesController::class, 'store'])->name('notes.store');
    Route::get('note/{clientNote}/show', [ClientNotesController::class, 'show'])->name('notes.show');
    Route::get('note/{clientNote}/edit', [ClientNotesController::class, 'edit'])->name('notes.edit');
    Route::put('note/{clientNote}/update', [ClientNotesController::class, 'update'])->name('notes.update');
    Route::delete('note/{clientNote}/destroy', [ClientNotesController::class, 'destroy'])->name('notes.destroy');
    //shareholders
    Route::get('{client}/shareholder', [ClientShareholdersController::class, 'index'])->name('shareholders.index');
    Route::get('{client}/shareholder/create', [ClientShareholdersController::class, 'create'])->name('shareholders.create');
    Route::post('{client}/shareholder/store', [ClientShareholdersController::class, 'store'])->name('shareholders.store');
    Route::get('shareholder/{shareholder}/show', [ClientShareholdersController::class, 'show'])->name('shareholders.show');
    Route::get('shareholder/{shareholder}/edit', [ClientShareholdersController::class, 'edit'])->name('shareholders.edit');
    Route::put('shareholder/{shareholder}/update', [ClientShareholdersController::class, 'update'])->name('shareholders.update');
    Route::delete('shareholder/{shareholder}/destroy', [ClientShareholdersController::class, 'destroy'])->name('shareholders.destroy');
    //balance sheet
    Route::get('{client}/balance_sheet', [ClientBalanceSheetController::class, 'index'])->name('balance_sheets.index');
    Route::get('{client}/balance_sheet/summary', [ClientBalanceSheetController::class, 'summary'])->name('balance_sheets.summary');
    Route::get('{client}/balance_sheet/create', [ClientBalanceSheetController::class, 'create'])->name('balance_sheets.create');
    Route::post('{client}/balance_sheet/store', [ClientBalanceSheetController::class, 'store'])->name('balance_sheets.store');
    Route::get('balance_sheet/{sheet}/show', [ClientBalanceSheetController::class, 'show'])->name('balance_sheets.show');
    Route::get('balance_sheet/{sheet}/edit', [ClientBalanceSheetController::class, 'edit'])->name('balance_sheets.edit');
    Route::put('balance_sheet/{sheet}/update', [ClientBalanceSheetController::class, 'update'])->name('balance_sheets.update');
    Route::delete('balance_sheet/{sheet}/destroy', [ClientBalanceSheetController::class, 'destroy'])->name('balance_sheets.destroy');
    //income statement
    Route::get('{client}/income_statement', [ClientIncomeController::class, 'index'])->name('income_statements.index');
    Route::get('{client}/income_statement/create', [ClientIncomeController::class, 'create'])->name('income_statements.create');
    Route::post('{client}/income_statement/store', [ClientIncomeController::class, 'store'])->name('income_statements.store');
    Route::get('income_statement/{statement}/show', [ClientIncomeController::class, 'show'])->name('income_statements.show');
    Route::get('income_statement/{statement}/edit', [ClientIncomeController::class, 'edit'])->name('income_statements.edit');
    Route::put('income_statement/{statement}/update', [ClientIncomeController::class, 'update'])->name('income_statements.update');
    Route::delete('income_statement/{statement}/destroy', [ClientIncomeController::class, 'destroy'])->name('income_statements.destroy');
    //poter
    Route::get('{client}/porter', [ClientPotersController::class, 'index'])->name('poter.index');
    Route::get('{client}/porter/create', [ClientPotersController::class, 'create'])->name('poter.create');
    Route::post('{client}/porter/store', [ClientPotersController::class, 'store'])->name('poter.store');
    Route::get('porter/{poter}/show', [ClientPotersController::class, 'show'])->name('poter.show');
    Route::get('porter/{poter}/edit', [ClientPotersController::class, 'edit'])->name('poter.edit');
    Route::put('porter/{poter}/update', [ClientPotersController::class, 'update'])->name('poter.update');
    Route::delete('porter/{poter}/destroy', [ClientPotersController::class, 'destroy'])->name('poter.destroy');

});

//loan applications
Route::group(['prefix' => 'loan_application', 'as' => 'loan_applications.'], function () {
    Route::get('/', [LoanApplicationsController::class, 'index'])->name('index');
    Route::get('/create', [LoanApplicationsController::class, 'create'])->name('create');
    Route::post('/store', [LoanApplicationsController::class, 'store'])->name('store');
    Route::post('/{application}/change_status', [LoanApplicationsController::class, 'changeStatus'])->name('change_status');
    Route::post('/{application}/assign_approver', [LoanApplicationsController::class, 'assignApprover'])->name('assign_approver');
    Route::get('/{application}/show', [LoanApplicationsController::class, 'show'])->name('show');
    Route::get('/{application}/edit', [LoanApplicationsController::class, 'edit'])->name('edit');
    Route::put('/{application}/update', [LoanApplicationsController::class, 'update'])->name('update');
    Route::delete('/{application}/destroy', [LoanApplicationsController::class, 'destroy'])->name('destroy');
    Route::get('/{application}/comments', [LoanApplicationsController::class, 'showComments'])->name('show_comments');
    Route::get('fixing', [LoanApplicationsController::class, 'fixing'])->name('fixing');
    Route::get('resend-email/{id}', [LoanApplicationsController::class, 'resendEmail'])->name('resend');
    Route::get('view-log-history/{id}', [LoanApplicationsController::class, 'viewLogHistory'])->name('view-log-history');

    // Contracts routes
    Route::prefix('contracts')->name('contracts.')->group(function () {
        Route::get('/', [ContractsController::class, 'index'])->name('index');
        Route::post('/import', [ContractsController::class, 'import'])->name('save-contract');
        Route::get('/download-sample', [ContractsController::class, 'downloadSample'])->name('download-sample');
    });

    Route::post('loan-applications/save-comment', [CommentController::class, 'saveComment'])
        ->name('save-comment');
    Route::post('loan-applications/save-reply', [CommentController::class, 'saveReply'])
        ->name('save-reply');

    //loan books
    Route::get('/loan-books', [LoanBookController::class, 'index'])->name('loan-book');
    Route::post('/save-loan-book', [LoanBookController::class, 'import'])->name('loan-book.save-loan-book');
    Route::get('/loan-books/import', [LoanBookController::class, 'createImport'])->name('loan-book.import.create');
    Route::post('/loan-books/import', [LoanBookController::class, 'import'])->name('loan-book.import.store');

    //TABLE COLUMNS


    //files
    Route::get('{loan}/file', [LoanFilesController::class, 'index'])->name('files.index');
    Route::get('{loan}/file/create', [LoanFilesController::class, 'create'])->name('files.create');
    Route::post('{loan}/file/store', [LoanFilesController::class, 'store'])->name('files.store');
    Route::get('file/{file}/show', [LoanFilesController::class, 'show'])->name('files.show');
    Route::get('file/{file}/edit', [LoanFilesController::class, 'edit'])->name('files.edit');
    Route::put('file/{file}/update', [LoanFilesController::class, 'update'])->name('files.update');
    Route::delete('file/{file}/destroy', [LoanFilesController::class, 'destroy'])->name('files.destroy');
    //notes
    Route::get('{loan}/note', [LoanNotesController::class, 'index'])->name('notes.index');
    Route::get('{loan}/note/create', [LoanNotesController::class, 'create'])->name('notes.create');
    Route::post('{loan}/note/store', [LoanNotesController::class, 'store'])->name('notes.store');
    Route::get('note/{note}/show', [LoanNotesController::class, 'show'])->name('notes.show');
    Route::get('note/{note}/edit', [LoanNotesController::class, 'edit'])->name('notes.edit');
    Route::put('note/{note}/update', [LoanNotesController::class, 'update'])->name('notes.update');
    Route::delete('note/{note}/destroy', [LoanNotesController::class, 'destroy'])->name('notes.destroy');
    //guarantors
    Route::get('{loan}/guarantor', [LoanGuarantorsController::class, 'index'])->name('guarantors.index');
    Route::get('{loan}/guarantor/create', [LoanGuarantorsController::class, 'create'])->name('guarantors.create');
    Route::post('{loan}/guarantor/store', [LoanGuarantorsController::class, 'store'])->name('guarantors.store');
    Route::get('guarantor/{guarantor}/show', [LoanGuarantorsController::class, 'show'])->name('guarantors.show');
    Route::get('guarantor/{guarantor}/edit', [LoanGuarantorsController::class, 'edit'])->name('guarantors.edit');
    Route::put('guarantor/{guarantor}/update', [LoanGuarantorsController::class, 'update'])->name('guarantors.update');
    Route::delete('guarantor/{guarantor}/destroy', [LoanGuarantorsController::class, 'destroy'])->name('guarantors.destroy');

    Route::prefix('category')->name('categories.')->group(function () {
        Route::get('/', [LoanProductCategoriesController::class, 'index'])->name('index');
        Route::get('/create', [LoanProductCategoriesController::class, 'create'])->name('create');
        Route::post('/store', [LoanProductCategoriesController::class, 'store'])->name('store');
        Route::get('/{category}/edit', [LoanProductCategoriesController::class, 'edit'])->name('edit');
        Route::put('/{category}/update', [LoanProductCategoriesController::class, 'update'])->name('update');
        Route::delete('/{category}/destroy', [LoanProductCategoriesController::class, 'destroy'])->name('destroy');
    });
});

//portfolios
Route::get('portfolios', [LoanPortfoliosController::class, 'index'])->name('portfolios.index');
Route::get('portfolios/create', [LoanPortfoliosController::class, 'create'])->name('portfolios.create');
Route::post('portfolios', [LoanPortfoliosController::class, 'store'])->name('portfolios.store');
Route::get('portfolios/{portfolio}/edit', [LoanPortfoliosController::class, 'edit'])->name('portfolios.edit');
Route::put('portfolios/{portfolio}', [LoanPortfoliosController::class, 'update'])->name('portfolios.update');
Route::delete('portfolios/{portfolio}', [LoanPortfoliosController::class, 'destroy'])->name('portfolios.destroy');

//files
Route::group(['prefix' => 'file'], function () {
    Route::get('/', [FilesController::class, 'index'])->name('files.index');
    Route::post('/upload', [FilesController::class, 'upload'])->name('files.upload');
    Route::get('/{file}/download', [FilesController::class, 'download'])->name('files.download');
    Route::get('/create', [FilesController::class, 'create'])->name('files.create');
    Route::post('/store', [FilesController::class, 'store'])->name('files.store');
    Route::get('/{id}/show', [FilesController::class, 'show'])->name('files.show');
    Route::get('/{id}/edit', [FilesController::class, 'edit'])->name('files.edit');
    Route::put('/{id}/update', [FilesController::class, 'update'])->name('files.update');
    Route::delete('/{id}/destroy', [FilesController::class, 'destroy'])->name('files.destroy');
});
//branches
Route::group(['prefix' => 'branch'], function () {
    Route::get('/', [BranchesController::class, 'index'])->name('branches.index');
    Route::get('/create', [BranchesController::class, 'create'])->name('branches.create');
    Route::post('/store', [BranchesController::class, 'store'])->name('branches.store');
    Route::get('/{branch}/show', [BranchesController::class, 'show'])->name('branches.show');
    Route::get('/{branch}/edit', [BranchesController::class, 'edit'])->name('branches.edit');
    Route::put('/{branch}/update', [BranchesController::class, 'update'])->name('branches.update');
    Route::delete('/{branch}/destroy', [BranchesController::class, 'destroy'])->name('branches.destroy');
});
//industry_types
Route::group(['prefix' => 'industry_type'], function () {
    Route::get('/', [IndustryTypesController::class, 'index'])->name('industry_types.index');
    Route::get('/create', [IndustryTypesController::class, 'create'])->name('industry_types.create');
    Route::post('/store', [IndustryTypesController::class, 'store'])->name('industry_types.store');
    Route::get('/{type}/show', [IndustryTypesController::class, 'show'])->name('industry_types.show');
    Route::get('/{type}/edit', [IndustryTypesController::class, 'edit'])->name('industry_types.edit');
    Route::put('/{type}/update', [IndustryTypesController::class, 'update'])->name('industry_types.update');
    Route::delete('/{type}/destroy', [IndustryTypesController::class, 'destroy'])->name('industry_types.destroy');
});
//legal_types
Route::group(['prefix' => 'legal_type'], function () {
    Route::get('/', [LegalTypesController::class, 'index'])->name('legal_types.index');
    Route::get('/create', [LegalTypesController::class, 'create'])->name('legal_types.create');
    Route::post('/store', [LegalTypesController::class, 'store'])->name('legal_types.store');
    Route::get('/{type}/show', [LegalTypesController::class, 'show'])->name('legal_types.show');
    Route::get('/{type}/edit', [LegalTypesController::class, 'edit'])->name('legal_types.edit');
    Route::put('/{type}/update', [LegalTypesController::class, 'update'])->name('legal_types.update');
    Route::delete('/{type}/destroy', [LegalTypesController::class, 'destroy'])->name('legal_types.destroy');
});
//chart_of_accounts
Route::group(['prefix' => 'chart_of_account'], function () {
    Route::get('/', [ChartOfAccountController::class, 'index'])->name('chart_of_accounts.index');
    Route::get('/create', [ChartOfAccountController::class, 'create'])->name('chart_of_accounts.create');
    Route::post('/store', [ChartOfAccountController::class, 'store'])->name('chart_of_accounts.store');
    Route::get('/{chartOfAccount}/show', [ChartOfAccountController::class, 'show'])->name('chart_of_accounts.show');
    Route::get('/{chartOfAccount}/edit', [ChartOfAccountController::class, 'edit'])->name('chart_of_accounts.edit');
    Route::put('/{chartOfAccount}/update', [ChartOfAccountController::class, 'update'])->name('chart_of_accounts.update');
    Route::delete('/{chartOfAccount}/destroy', [ChartOfAccountController::class, 'destroy'])->name('chart_of_accounts.destroy');
});
//banks
Route::group(['prefix' => 'bank'], function () {
    Route::get('/', [BanksController::class, 'index'])->name('banks.index');
    Route::get('/create', [BanksController::class, 'create'])->name('banks.create');
    Route::post('/store', [BanksController::class, 'store'])->name('banks.store');
    Route::get('/{bank}/show', [BanksController::class, 'show'])->name('banks.show');
    Route::get('/{bank}/edit', [BanksController::class, 'edit'])->name('banks.edit');
    Route::put('/{bank}/update', [BanksController::class, 'update'])->name('banks.update');
    Route::delete('/{bank}/destroy', [BanksController::class, 'destroy'])->name('banks.destroy');
});
//locations
Route::group(['prefix' => 'location', 'as' => 'locations.'], function () {
    //regions for swaziland
    Route::group(['prefix' => 'region'], function () {
        Route::get('/', [RegionsController::class, 'index'])->name('regions.index');
        Route::get('/create', [RegionsController::class, 'create'])->name('regions.create');
        Route::post('/store', [RegionsController::class, 'store'])->name('regions.store');
        Route::get('/{province}/show', [RegionsController::class, 'show'])->name('regions.show');
        Route::get('/{province}/edit', [RegionsController::class, 'edit'])->name('regions.edit');
        Route::put('/{province}/update', [RegionsController::class, 'update'])->name('regions.update');
        Route::delete('/{province}/destroy', [RegionsController::class, 'destroy'])->name('regions.destroy');
    });
    Route::group(['prefix' => 'province'], function () {
        Route::get('/', [ProvincesController::class, 'index'])->name('provinces.index');
        Route::get('/create', [ProvincesController::class, 'create'])->name('provinces.create');
        Route::post('/store', [ProvincesController::class, 'store'])->name('provinces.store');
        Route::get('/{province}/show', [ProvincesController::class, 'show'])->name('provinces.show');
        Route::get('/{province}/edit', [ProvincesController::class, 'edit'])->name('provinces.edit');
        Route::put('/{province}/update', [ProvincesController::class, 'update'])->name('provinces.update');
        Route::delete('/{province}/destroy', [ProvincesController::class, 'destroy'])->name('provinces.destroy');
    });
    //inkhundla
    Route::group(['prefix' => 'inkhundla'], function () {
        Route::get('/', [InkhundlaController::class, 'index'])->name('inkhundla.index');
        Route::get('/create', [InkhundlaController::class, 'create'])->name('inkhundla.create');
        Route::post('/store', [InkhundlaController::class, 'store'])->name('inkhundla.store');
        Route::get('/{district}/show', [InkhundlaController::class, 'show'])->name('inkhundla.show');
        Route::get('/{district}/edit', [InkhundlaController::class, 'edit'])->name('inkhundla.edit');
        Route::put('/{district}/update', [InkhundlaController::class, 'update'])->name('inkhundla.update');
        Route::delete('/{district}/destroy', [InkhundlaController::class, 'destroy'])->name('inkhundla.destroy');
    });
    //districts
    Route::group(['prefix' => 'district'], function () {
        Route::get('/', [DistrictsController::class, 'index'])->name('districts.index');
        Route::get('/create', [DistrictsController::class, 'create'])->name('districts.create');
        Route::post('/store', [DistrictsController::class, 'store'])->name('districts.store');
        Route::get('/{district}/show', [DistrictsController::class, 'show'])->name('districts.show');
        Route::get('/{district}/edit', [DistrictsController::class, 'edit'])->name('districts.edit');
        Route::put('/{district}/update', [DistrictsController::class, 'update'])->name('districts.update');
        Route::delete('/{district}/destroy', [DistrictsController::class, 'destroy'])->name('districts.destroy');
    });
    //wards
    Route::group(['prefix' => 'ward'], function () {
        Route::get('/', [WardsController::class, 'index'])->name('wards.index');
        Route::get('/create', [WardsController::class, 'create'])->name('wards.create');
        Route::post('/store', [WardsController::class, 'store'])->name('wards.store');
        Route::get('/{ward}/show', [WardsController::class, 'show'])->name('wards.show');
        Route::get('/{ward}/edit', [WardsController::class, 'edit'])->name('wards.edit');
        Route::put('/{ward}/update', [WardsController::class, 'update'])->name('wards.update');
        Route::delete('/{ward}/destroy', [WardsController::class, 'destroy'])->name('wards.destroy');
    });
    //villages
    Route::group(['prefix' => 'village'], function () {
        Route::get('/', [VillagesController::class, 'index'])->name('villages.index');
        Route::get('/create', [VillagesController::class, 'create'])->name('villages.create');
        Route::post('/store', [VillagesController::class, 'store'])->name('villages.store');
        Route::get('/{village}/show', [VillagesController::class, 'show'])->name('villages.show');
        Route::get('/{village}/edit', [VillagesController::class, 'edit'])->name('villages.edit');
        Route::put('/{village}/update', [VillagesController::class, 'update'])->name('villages.update');
        Route::delete('/{village}/destroy', [VillagesController::class, 'destroy'])->name('villages.destroy');
    });
});
//currencies
Route::group(['prefix' => 'currency'], function () {
    Route::get('/', [CurrenciesController::class, 'index'])->name('currencies.index');
    Route::get('/create', [CurrenciesController::class, 'create'])->name('currencies.create');
    Route::post('/store', [CurrenciesController::class, 'store'])->name('currencies.store');
    Route::get('/{currency}/show', [CurrenciesController::class, 'show'])->name('currencies.show');
    Route::get('/{currency}/edit', [CurrenciesController::class, 'edit'])->name('currencies.edit');
    Route::put('/{currency}/update', [CurrenciesController::class, 'update'])->name('currencies.update');
    Route::delete('/{currency}/destroy', [CurrenciesController::class, 'destroy'])->name('currencies.destroy');
});
//approval stages
Route::group(['prefix' => 'loan_approval_stage'], function () {
    Route::get('/', [LoanApprovalStagesController::class, 'index'])->name('loan_approval_stages.index');
    Route::get('/create', [LoanApprovalStagesController::class, 'create'])->name('loan_approval_stages.create');
    Route::post('/store', [LoanApprovalStagesController::class, 'store'])->name('loan_approval_stages.store');
    Route::get('/{stage}/show', [LoanApprovalStagesController::class, 'show'])->name('loan_approval_stages.show');
    Route::get('/{stage}/edit', [LoanApprovalStagesController::class, 'edit'])->name('loan_approval_stages.edit');
    Route::put('/{stage}/update', [LoanApprovalStagesController::class, 'update'])->name('loan_approval_stages.update');
    Route::delete('/{stage}/destroy', [LoanApprovalStagesController::class, 'destroy'])->name('loan_approval_stages.destroy');
});

Route::group(['prefix' => 'financial_period'], function () {
    Route::get('/', [FinancialPeriodController::class, 'index'])->name('accounting.financial_periods.index');
    Route::get('/create', [FinancialPeriodController::class, 'create'])->name('accounting.financial_periods.create');
    Route::post('/store', [FinancialPeriodController::class, 'store'])->name('accounting.financial_periods.store');
    Route::get('/{financialPeriod}/show', [FinancialPeriodController::class, 'show'])->name('accounting.financial_periods.show');
    Route::get('/{financialPeriod}/edit', [FinancialPeriodController::class, 'edit'])->name('accounting.financial_periods.edit');
    Route::put('/{financialPeriod}/update', [FinancialPeriodController::class, 'update'])->name('accounting.financial_periods.update');
    Route::put('/{financialPeriod}/close', [FinancialPeriodController::class, 'close'])->name('accounting.financial_periods.close');
    Route::delete('/{financialPeriod}/destroy', [FinancialPeriodController::class, 'destroy'])->name('accounting.financial_periods.destroy');
});
//communication
Route::prefix('communication')->group(function () {
    Route::get('/', [CommunicationController::class, 'index']);
    //sms gateway
    Route::group(['prefix' => 'sms_gateway'], function () {
        Route::get('/', [SmsGatewaysController::class, 'index'])->name('communication.sms_gateways.index');
        Route::get('/create', [SmsGatewaysController::class, 'create'])->name('communication.sms_gateways.create');
        Route::post('/store', [SmsGatewaysController::class, 'store'])->name('communication.sms_gateways.store');
        Route::get('/{smsGateway}/show', [SmsGatewaysController::class, 'show'])->name('communication.sms_gateways.show');
        Route::get('/{smsGateway}/edit', [SmsGatewaysController::class, 'edit'])->name('communication.sms_gateways.edit');
        Route::put('/{smsGateway}/update', [SmsGatewaysController::class, 'update'])->name('communication.sms_gateways.update');
        Route::delete('/{smsGateway}/destroy', [SmsGatewaysController::class, 'destroy'])->name('communication.sms_gateways.destroy');
    });
    Route::prefix('campaign')->group(function () {
        Route::get('/', [CommunicationCampaignController::class, 'index'])->name('communication.campaigns.index');
        Route::get('/create', [CommunicationCampaignController::class, 'create'])->name('communication.campaigns.create');
        Route::post('/store', [CommunicationCampaignController::class, 'store'])->name('communication.campaigns.store');
        Route::get('/{communicationCampaign}/show', [CommunicationCampaignController::class, 'show'])->name('communication.campaigns.show');
        Route::get('/{communicationCampaign}/edit', [CommunicationCampaignController::class, 'edit'])->name('communication.campaigns.edit');
        Route::put('/{communicationCampaign}/update', [CommunicationCampaignController::class, 'update'])->name('communication.campaigns.update');
        Route::delete('/{communicationCampaign}/destroy', [CommunicationCampaignController::class, 'destroy'])->name('communication.campaigns.destroy');
    });
    Route::prefix('template')->group(function () {
        Route::get('/', [CommunicationTemplateController::class, 'index'])->name('communication.templates.index');
        Route::get('/create', [CommunicationTemplateController::class, 'create'])->name('communication.templates.create');
        Route::post('/store', [CommunicationTemplateController::class, 'store'])->name('communication.templates.store');
        Route::get('/{template}/show', [CommunicationTemplateController::class, 'show'])->name('communication.templates.show');
        Route::get('/{template}/edit', [CommunicationTemplateController::class, 'edit'])->name('communication.templates.edit');
        Route::put('/{template}/update', [CommunicationTemplateController::class, 'update'])->name('communication.templates.update');
        Route::delete('/{template}/destroy', [CommunicationTemplateController::class, 'destroy'])->name('communication.templates.destroy');
    });
    Route::prefix('log')->group(function () {
        Route::get('/', [CommunicationLogController::class, 'index'])->name('communication.logs.index');
        Route::get('{communicationCampaignLog}/destroy', [CommunicationLogController::class, 'destroy'])->name('communication.logs.destroy');
    });
});

//settings
Route::group(['prefix' => 'setting'], function () {
    Route::get('/', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/organisation', [SettingsController::class, 'organisation'])->name('settings.organisation');
    Route::get('/general', [SettingsController::class, 'general'])->name('settings.general');
    Route::post('/general/update', [SettingsController::class, 'generalUpdate'])->name('settings.general.update');
    Route::get('/system', [SettingsController::class, 'system'])->name('settings.system');
    Route::post('/system/update', [SettingsController::class, 'systemUpdate'])->name('settings.system.update');
    Route::get('/email', [SettingsController::class, 'email'])->name('settings.email');
    Route::post('/email/update', [SettingsController::class, 'emailUpdate'])->name('settings.email.update');
    Route::get('/sms', [SettingsController::class, 'sms'])->name('settings.sms');
    Route::post('/sms/update', [SettingsController::class, 'smsUpdate'])->name('settings.sms.update');
    Route::get('/other', [SettingsController::class, 'other'])->name('settings.other');
    Route::post('/other/update', [SettingsController::class, 'otherUpdate'])->name('settings.other.update');
    Route::get('/billing', [SettingsController::class, 'billing'])->name('settings.billing');
    Route::post('/update', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/loan-bands', [SettingsController::class, 'loanBands'])->name('settings.loan_bands');
    Route::post('/store-loan-bands', [SettingsController::class, 'storeLoanBands'])->name('settings.other.store-bands');
});
//reports
Route::group(['prefix' => 'report'], function () {
    Route::get('/', [ReportsController::class, 'index'])->name('reports.index');

});
//license
Route::group(['prefix' => 'license'], function () {
    Route::get('/', [LicenseController::class, 'index'])->name('license.index');
    Route::post('/verify', [LicenseController::class, 'verify'])->name('license.verify');
});

//client portal
Route::group(['prefix' => 'portal', 'as' => 'portal.'], function () {
    Route::get('/', [MemberPortalUsersController::class, 'dashboard'])->name('home');
    Route::get('/dashboard', [MemberPortalUsersController::class, 'dashboard'])->name('dashboard');
    Route::get('/search_clients', [MemberPortalUsersController::class, 'searchMembers'])->name('search_clients');
    //user
    Route::group(['prefix' => 'user'], function () {
        Route::get('profile', [MemberPortalUsersController::class, 'profile'])->name('profile.show');

    });

});


Route::get('/imports', [GeneralImportController::class, 'index'])->name('custom_imports.index');
Route::get('/create', [GeneralImportController::class, 'create'])->name('custom_imports.create');
Route::post('/store', [GeneralImportController::class, 'store'])->name('custom_imports.store');
Route::get('/{customImport}/show', [GeneralImportController::class, 'show'])->name('custom_imports.show');
Route::get('/{customImport}/edit', [GeneralImportController::class, 'edit'])->name('custom_imports.edit');
Route::put('/{customImport}/update', [GeneralImportController::class, 'update'])->name('custom_imports.update');
Route::delete('/{customImport}/destroy', [GeneralImportController::class, 'destroy'])->name('custom_imports.destroy');
Route::get('/imports/{template}/sample', [GeneralImportController::class, 'downloadSample'])->name('custom_imports.sample');



// Transition Matrix Routes
Route::group(['prefix' => 'transition-matrix', 'as' => 'transition-matrices.'], function () {
    Route::get('/', [TransitionMatrixController::class, 'index'])->name('index');
    Route::get('/create', [TransitionMatrixController::class, 'create'])->name('create');
    Route::post('/store', [TransitionMatrixController::class, 'store'])->name('store');
    Route::get('/{matrix}/show', [TransitionMatrixController::class, 'show'])->name('show');
    Route::post('/{matrix}/update-loan-book', [TransitionMatrixController::class, 'updateLoanBook'])->name('update-loan-book');

    // Matrix Entries Routes
    Route::get('/{matrix}/entries', [TransitionMatrixController::class, 'entries'])->name('entries.index');
    Route::put('/{matrix}/entries', [TransitionMatrixController::class, 'updateEntries'])->name('entries.update');
    Route::post('/transition-matrices/{matrix}/entries', [TransitionMatrixController::class, 'updateEntries'])->name('save-entries.update');

    Route::get('/{matrix}/view', [TransitionMatrixController::class, 'view'])->name('view');
    Route::get('/{matrix}/edit', [TransitionMatrixController::class, 'edit'])->name('edit');
    Route::post('/{matrix}/rerun', [TransitionMatrixController::class, 'rerun'])->name('rerun');

    Route::get('/{matrix}/data', [TransitionMatrixController::class, 'getData']);

    Route::post('/{matrix}/update-data', [TransitionMatrixController::class, 'updateData']);
    Route::post('/{matrix}/rerun', [TransitionMatrixController::class, 'rerun']);
    Route::post('/{matrix}/update-loanbook', [TransitionMatrixController::class, 'updateLoanBook'])->name('matrix.loanbook-update');
    Route::post('/{matrix}/lock-pd',[TransitionMatrixController::class,'keyLock'])->name('lock');

});


// Transition Matrix Routes
// Route::get('/transition-matrices', [TransitionMatrixController::class, 'index'])->name('transition-matrices.index');
// Route::get('/transition-matrices/create', [TransitionMatrixController::class, 'create'])->name('transition-matrices.create');
// Route::post('/transition-matrices', [TransitionMatrixController::class, 'store'])->name('transition-matrices.store');
// Route::get('/transition-matrices/{matrix}', [TransitionMatrixController::class, 'show'])->name('transition-matrices.show');
// Route::get('/transition-matrices/{matrix}/download', [TransitionMatrixController::class, 'download'])->name('transition-matrices.download');
// Route::post('/transition-matrices/{matrix}/update-loan-book', [TransitionMatrixController::class, 'updateLoanBook'])->name('transition-matrices.update-loan-book');
Route::group(['prefix' => 'import', 'as' => 'imports.'], function () {
    Route::get('/', [ImportsController::class, 'index'])->name('index');
});
Route::get('/imports/download-failed/{import}', [ImportsController::class, 'downloadFailedFile'])->name('imports.failed-download');


Route::get('migrate', function () {
    Artisan::call('migrate', ['--force' => true]);
    return redirect()->route('dashboard')->with('success', 'Migrated successfully.');
});

Route::get('cache', function () {
    Artisan::call('cache:clear', ['--force' => true]);
    Artisan::call('route:clear', ['--force' => true]);
    Artisan::call('config:clear', ['--force' => true]);
    Artisan::call('optimize', ['--force' => true]);
    return redirect()->route('dashboard')->with('success', 'cache cleared successfully.');
});

// Transition Matrix Cummulative

Route::get('/transition-matrix-cummulative', [TransitionMatrixCummulativeController::class, 'index'])->name('transition-matrix-cummulative.index');
Route::get('/transition-matrix-cummulative/create', [TransitionMatrixCummulativeController::class, 'create'])->name('transition-matrix-cummulative.create');
Route::post('/transition-matrix-cummulative', [TransitionMatrixCummulativeController::class, 'store'])->name('transition-matrix-cummulative.store');
Route::post('/transition-matrix-cummulative/{matrix}/rerun', [TransitionMatrixCummulativeController::class, 'rerun'])->name('transition-matrix-cummulative.rerun');
Route::post('/transition-matrix-cumulative/{matrix}/update',[TransitionMatrixCummulativeController::class,'updateLoanBook'])->name('transition-matrix-cummulative.update-loan-book');
Route::get('/transition-matrix-cumulative/{matrix}/data', [TransitionMatrixCummulativeController::class, 'getData']);
Route::post('/transition-matrix-cumulative/{matrix}/lock-pd',[TransitionMatrixCummulativeController::class,'keyLock'])->name('transition-matrix-cumulative.lock');



//Transition Profile Definitions
// Route::group(['prefix' => 'transition-profile-definitions', 'as' => 'transition-profile-definitions.'], function () {
//     Route::get('/', [TransitionProfileDefinitionController::class, 'index'])->name('index');
//     Route::get('/create', [TransitionProfileDefinitionController::class, 'create'])->name('create');
//     Route::post('/', [TransitionProfileDefinitionController::class, 'store'])->name('store');
//     Route::get('/{definition}/edit', [TransitionProfileDefinitionController::class, 'edit'])->name('edit');
//     Route::put('/{definition}', [TransitionProfileDefinitionController::class, 'update'])->name('update');
//     Route::delete('/{definition}', [TransitionProfileDefinitionController::class, 'destroy'])->name('destroy');
// });



// Route::resource('transition-profile-definitions', TransitionProfileDefinitionController::class); // This will cover index, store, update, and destroy routes


// Custom API Routes for DataTable
Route::middleware(['auth'])->group(function () {
    Route::get('/transition-profiles', [TransitionProfileDefinitionController::class, 'index'])->name('transition-profiles.index');
Route::get('/transition-profiles/create', [TransitionProfileDefinitionController::class, 'create'])->name('transition-profiles.create')->middleware('auth');

    Route::post('/transition-profiles', [TransitionProfileDefinitionController::class, 'store'])->name('transition-profiles.store');
    Route::get('/transition-profiles/{id}/edit', [TransitionProfileDefinitionController::class, 'edit'])->name('transition-profiles.edit');
    Route::put('/transition-profiles/update/{id}', [TransitionProfileDefinitionController::class, 'update'])->name('transition-profiles.update');
    Route::delete('/transition-profiles/delete/{id}', [TransitionProfileDefinitionController::class, 'destroy']);
});

// Separate route for DataTables AJAX request
Route::get('/api/transition-profiles', [TransitionProfileDefinitionController::class, 'getProfiles']); // For DataTables data
Route::get('/api/transition-profiles/tables', [TransitionProfileDefinitionController::class, 'getTables']); // For getting tables
Route::get('/api/transition-profiles/columns', [TransitionProfileDefinitionController::class, 'getColumns']);


// Transition Profile Options

Route::get('/transition-profile/categories/{profileId}', [TransitionProfileOptionController::class, 'categories'])->name('transition-profile.categories');
Route::get('/transition-profiles/{id}/config', [TransitionProfileOptionController::class, 'index'])->name('transition-profiles.config');
Route::post('/transition-profile/categories', [TransitionProfileOptionController::class, 'store']);
Route::post('/transition-profile/categories/reorder', [TransitionProfileOptionController::class, 'sortingIndex']);
Route::delete('/transition-profile/categories/{id}/delete', [TransitionProfileOptionController::class, 'destroy']);
Route::put('/transition-profile/categories/{id}', [TransitionProfileOptionController::class, 'update']);


// LGD Routes

Route::get('/loss-given-default/list',[LossGiveDefaultController::class,'index'])->name('loss-given-default.index');
Route::get('/loss-given-default/create',[LossGiveDefaultController::class,'create'])->name('loss-given-default.create');
Route::post('loss-given-default/calculations',[LossGiveDefaultController::class,'calculateLGD'])->name('loss-given-default.systemCalculation');
//Route::get('/loss-given-default/manual-calculation', [LossGiveDefaultController::class,'editManual'])->name('loss-given-default.editManual');
Route::put('/loss-given-default/update/{id}', [LossGiveDefaultController::class,'updateManualCalculation'])->name('loss-given-default.updateManual');
Route::post('/loss-given-default/manual-calculation', [LossGiveDefaultController::class,'storeManualCalculation'])->name('loss-given-default.storeManual');
Route::delete('/loss-given-default/delete/{id}', [LossGiveDefaultController::class,'destroy'])->name('loss-given-default.delete');
Route::put('/loss-given-default/update/{id}', [LossGiveDefaultController::class,'updateManualCalculation'])->name('loss-given-default.updateManual');
Route::post('/loss-given-default/update-loanbook', [LossGiveDefaultController::class, 'updateLoanBooks'])->name('loss-given-default.update-loan-book');
Route::get('/loss-given-default/{id}/edit', [LossGiveDefaultController::class,'editManual'])->name('loss-given-default.editManual');
Route::middleware(['auth'])->group(function () {
    Route::post('/loss-given-default/{id}/lock', [LossGiveDefaultController::class, 'keyLock'])->name('loss-given-default.lock');
});
// LGD Cummulative Routes

Route::get('/loss-given-default/cummulative', [LossGivenDefaultCummulativeController::class,'index'])->name('lgd-cummulative.index');
Route::get('/loss-given-default/cummulative/create', [LossGivenDefaultCummulativeController::class, 'create'])->name('lgd-cummulative.create');
Route::post('/loss-given-default/cummulative/calculations',[LossGivenDefaultCummulativeController::class, 'cummulativeLGD'])->name('lgd-cummulative.system');
Route::post('/loss-given-default/cummulative/manual-calculation', [LossGivenDefaultCummulativeController::class, 'manualCummulativeLGD'])->name('lgd-cummulative.manual');
Route::post('/loss-given-default/cummulative/update-loanbook',[LossGivenDefaultCummulativeController::class, 'updateLoanBooks'])->name('lgd-cummulative.update-loanbook');
Route::delete('/loss-given-default/cummulative/{id}/delete',[LossGivenDefaultCummulativeController::class,'destroy'])->name('lgd-cummulative.delete');
Route::middleware(['auth'])->group(function () {
    Route::post('/loss-given-default/cummulative/{id}/lock', [LossGivenDefaultCummulativeController::class, 'keyLock'])->name('lgd-cummulative.lock');
});

// ECL Routes
Route::get('/expected-credit-loss/list', [ExpectedCreditLossController::class, 'index'])->name('expected-credit-loss.index');
Route::get('/expected-credit-loss/create', [ExpectedCreditLossController::class, 'create'])->name('expected-credit-loss.create');
Route::post('/expected-credit-loss/calculations', [ExpectedCreditLossController::class, 'calculateECL'])->name('expected-credit-loss.calculation');
Route::get('/expected-credit-loss/reports',[ExpectedCreditLossController::class,'exportECL'])->name('expected-credit-loss.reports');

// Stageing Rules Routes
Route::group(['prefix' => 'stageing-rules', 'as' => 'stageing-rules.'], function () {
    Route::get('/', [\App\Http\Controllers\StageingRulesController::class, 'index'])->name('index');
    Route::post('/store', [\App\Http\Controllers\StageingRulesController::class, 'store'])->name('store');
});

Route::group(['prefix' => 'sicr-groups', 'as' => 'sicr-groups.'], function () {
    Route::get('/', [\App\Http\Controllers\SicrGroupController::class, 'index'])->name('index');
    Route::post('/store', [\App\Http\Controllers\SicrGroupController::class, 'store'])->name('store');
    Route::post('/import', [\App\Http\Controllers\SicrGroupController::class, 'import'])->name('import');
    Route::put('/{group}/update', [\App\Http\Controllers\SicrGroupController::class, 'update'])->name('update');
    Route::delete('/{group}/destroy', [\App\Http\Controllers\SicrGroupController::class, 'destroy'])->name('destroy');
});

Route::group(['prefix' => 'sicr-items', 'as' => 'sicr-items.'], function () {
    Route::get('/', [\App\Http\Controllers\SicrItemController::class, 'index'])->name('index');
    Route::post('/store', [\App\Http\Controllers\SicrItemController::class, 'store'])->name('store');
    Route::post('/import', [\App\Http\Controllers\SicrItemController::class, 'import'])->name('import');
    Route::put('/{item}/update', [\App\Http\Controllers\SicrItemController::class, 'update'])->name('update');
    Route::post('/{item}/toggle', [\App\Http\Controllers\SicrItemController::class, 'toggle'])->name('toggle');
    Route::delete('/{item}/destroy', [\App\Http\Controllers\SicrItemController::class, 'destroy'])->name('destroy');
});

Route::group(['prefix' => 'sicr-triggers', 'as' => 'sicr-triggers.'], function () {
    Route::get('/', [\App\Http\Controllers\SicrTriggerController::class, 'index'])->name('index');
    Route::post('/store', [\App\Http\Controllers\SicrTriggerController::class, 'store'])->name('store');
    Route::post('/{trigger}/update-loan-book', [\App\Http\Controllers\SicrTriggerController::class, 'updateLoanBook'])->name('update-loan-book');
    Route::post('/{trigger}/remove-alert', [\App\Http\Controllers\SicrTriggerController::class, 'removeAlert'])->name('remove-alert');
    Route::get('/customers', [\App\Http\Controllers\SicrTriggerController::class, 'getCustomers'])->name('customers');
});

//Manual Routes
Route::get('/manuals/list',[ManualsController::class,'index'])->name('manuals.index');
Route::get('/manuals/create',[ManualsController::class,'create'])->name('manuals.create');
Route::post('/manuals', [ManualsController::class, 'store'])->name('manuals.store');
Route::get('/manuals/{manual}/edit', [ManualsController::class, 'edit'])->name('manuals.edit');
Route::get('/manuals/{manual}', [ManualsController::class, 'show'])->name('manuals.show');
Route::put('/manuals/{manual}/update',[ManualsController::class, 'update'])->name('manuals.update');
Route::delete('/manuals/{manual}',[ManualsController::class,'destroy'])->name('manuals.delete');


// Foward Looking Information [ Macro Statistic ]

Route::get('/macro-statistics', [MacroStatsController::class, 'index'])->name('macro-statistics.index');
Route::post('/macro-statistics', [MacroStatsController::class, 'store'])->name('macro-statistics.store');
Route::put('/macro-statistics/{id}', [MacroStatsController::class, 'update'])->name('macro-statistics.update');
Route::delete('/macro-statistics/{id}', [MacroStatsController::class, 'destroy'])->name('macro-statistics.destroy');

// Macro Values Routes
Route::middleware(['auth'])->group(function () {
Route::get('/macro-statistics/{stat}/values', [MacroStatsValueController::class, 'index'])->name('macro-values.index');
Route::post('/macro-statistics/{stat}/values', [MacroStatsValueController::class, 'store'])->name('macro-values.store');
Route::put('/macro-statistics/values/{value}', [MacroStatsValueController::class, 'update'])->name('macro-values.update');
Route::delete('/macro-statistics/values/{value}', [MacroStatsValueController::class, 'destroy'])->name('macro-values.destroy');
});    

// Scenarios Routes
Route::prefix('scenarios')->group(function () {
    Route::get('/{id}', [ScenariosController::class, 'index'])->name('scenarios.index');
    Route::post('/', [ScenariosController::class, 'store'])->name('scenarios.store');
    Route::put('/{scenario}', [ScenariosController::class, 'update'])->name('scenarios.update');
    Route::delete('/{scenario}', [ScenariosController::class, 'destroy'])->name('scenarios.destroy');
    }); 

Route::get('/scenario-profiles', [ScenariosController::class, 'profiles'])->name('scenarios.profiles');
Route::post('/scenario-profiles', [ScenariosController::class, 'storeProfile'])->name('scenarios.profiles.store');
Route::put('/scenario-profiles/{profile}', [ScenariosController::class, 'updateProfile'])->name('scenarios.profiles.update');

// Forecast Routes
Route::get('/macro-forecast-weighted', [MacroForecastWeightedController::class, 'index'])->name('macro-forecast-weighted.index');
Route::post('/macro-forecast-weighted', [MacroForecastWeightedController::class, 'store'])->name('macro-forecast-weighted.store');
Route::post('/macro-forecast-weighted/calculate', [MacroForecastWeightedController::class, 'calculate'])->name('macro-forecast-weighted.calculate');
Route::post('/macro-forecast-weighted/{id}/rerun', [MacroForecastWeightedController::class, 'rerun'])->name('macro-forecast-weighted.rerun');
Route::delete('/macro-forecast-weighted/{id}', [MacroForecastWeightedController::class, 'destroy'])->name('macro-forecast-weighted.destroy');