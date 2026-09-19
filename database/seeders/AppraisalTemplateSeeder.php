<?php

namespace Database\Seeders;

use App\Models\AppraisalTemplate;
use App\Models\AppraisalTemplateKpi;
use Illuminate\Database\Seeder;

/**
 * The balanced scorecards the company already uses, as templates.
 *
 * Taken from `BSC - HRM - 2024.xlsx` — four real cards that have been filled in
 * by hand for years — rather than invented. The key result areas, the measures,
 * the targets and the weights are the company's own words and numbers.
 *
 * The four perspectives and their shape come straight from those sheets:
 *
 *     1. FINANCIALS
 *     2. CUSTOMER PERSPECTIVE
 *     3. INTERNAL BUSINESS PROCESS
 *     4. LEARNING & GROWTH
 *
 * Each KRA carries a weighting, every rating is 1-5, and the weighted index is
 * rating x weighting — which is exactly what `AppraisalKpi::recalculate()`
 * already does, so the spreadsheets and the system agree without either being
 * bent to fit.
 *
 * The scale, from the sheets:
 *
 *     1 Poor (below 50%)    2 Fair (51-65%)    3 Good (66-75%)
 *     4 Very Good (76-95%)  5 Excellent (96%+)
 *
 * **Two of the four do not add up to 100%, and are seeded inactive.** Sales
 * Executive totals 96.5% and Bid Compiler 79.5%; on both, the per-perspective
 * headers disagree with the sum of the KPIs beneath them. Those are faults in
 * the source documents, not in the import. Scoring a card that totals 79.5%
 * produces an overall percentage out of 79.5 while presenting itself as out of
 * 100, so they are loaded — with their real numbers, unaltered — and left switched
 * off until somebody who owns the role corrects the weights in the builder.
 *
 * Nothing here is normalised to make it balance. Quietly scaling somebody's
 * weights to 100% would change what each objective is worth without anybody
 * agreeing to it.
 *
 * Idempotent: re-running updates the templates in place and never duplicates.
 */
class AppraisalTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $this->template(
            name: "Human Resources Manager",
            jobTitle: "Human Resources Manager",
            description: "From the Biz Dev't scorecard in BSC - HRM - 2024.xlsx.",
            // Derived from the KPIs beneath each perspective rather than the
            // sheet's printed header, so the template is internally consistent.
            weights: ['financial' => 15.0, 'customer' => 30.0, 'internal_process' => 30.0, 'learning_growth' => 25.0],
            isActive: true,
            kpis: [
            ["financial", "Cost Saving Initiatives to ensure reduction in Departmental Expenses", "15% reduction in Departmental costs", "1", 10.0, 1],
            ["financial", "New Staff renumerations", "Atleast 15% reduction on expected amount", "1", 5.0, 2],
            ["customer", "Resolution of Staff Complaints", "No of complaints closed Vs Received", "1", 5.0, 3],
            ["customer", "Implementation of Staff Rewards program", "Monthly Recognition and Quarterly Rewards", "1", 10.0, 4],
            ["customer", "Organisational Culture Implementation", "Monthly inititatives to drive Organisational Culture", "3", 5.0, 5],
            ["customer", "Staff Satisfaction Surveys", "100% Roll out of satsifaction surveys, analyse and share results for Management actioning - Quarterly.", "0.85", 5.0, 6],
            ["customer", "Feedback", "Attainment of a 95% customer satisfaction rating based on Staff Survey results. Show ratings >+95% - Score 100%, 94 to 90 - Score 90%, 89 to 85 - Score 85%, 84 to 80 - Score 80% Others 50% if survey was done.", "0.95", 5.0, 7],
            ["internal_process", "Implementation of HR Manual", "Communications and Tits bits of topics within manual content every week and quarterly trainings ( 12 communications and 1 training per quarter)", "1", 5.0, 8],
            ["internal_process", "Implementation of HRIS", "Availibility of a functional HRIS", "1", 4.0, 9],
            ["internal_process", "Develop Performance Parameters staff", "Fully Signed and filed BSCs for all staff", "1", 6.0, 10],
            ["internal_process", "Contractor Management - Reviews for compliance and performance by 15th of each Month", "Contract Review and Performance reports Monthly (Timely Submission 20%, Report 80%)", "3", 3.0, 11],
            ["internal_process", "Operational Reporting ( 12 weekly reports and 3 Monthly Reports)", "Submit monthly reports by 5th of the next month - 3 reports per quarter Weekly reporting every Monday (20% for timeliness & 80% for reports)", "1", 4.0, 12],
            ["internal_process", "Employee Relations Management", "Signed Warning Letters, Disciplinary hearing minutes and grievance hearing minutes for all incidences reported", "1", 5.0, 13],
            ["internal_process", "Workplans", "Submit Quarterly Workplans by 25th of last month of the current quarter for the next quarter", "0.85", 3.0, 14],
            ["learning_growth", "Training Development Plans", "85% execution of annual training plan", "1", 8.0, 15],
            ["learning_growth", "Performance Management for poor performance", "PIPs/ Perfromance hearings conducted for all reported poor performance cases", "1", 8.0, 16],
            ["learning_growth", "Succession planning at all fronts for direct reports", "Updated Succession matrix showing development strategy and its implementation", "3", 5.0, 17],
            ["learning_growth", "Quarterly Town Hall Meeting", "Signed off Minutes by CEO and HRM", "3", 4.0, 18],
            ["learning_growth", "1 Poor; ( Below 50%)", "4 = Very Good (76-95%)", "5 = Excellent (above 96%)", 0, 19],
            ],
        );

        $this->template(
            name: "Sales & Distribution",
            jobTitle: "Distribution Officer",
            description: "From the Sales & Distribution scorecard in BSC - HRM - 2024.xlsx.",
            // Derived from the KPIs beneath each perspective rather than the
            // sheet's printed header, so the template is internally consistent.
            weights: ['financial' => 30.0, 'customer' => 20.0, 'internal_process' => 40.0, 'learning_growth' => 10.0],
            isActive: true,
            kpis: [
            ["financial", "Reduce cost of delivery", "% reduction", "0.1", 20.0, 1],
            ["financial", "Compliance to vehicle maintenance budget", "% compliance", "0.95", 5.0, 2],
            ["financial", "Mitigate against accident costs", "Costs (where driver is liable)", "Zero", 5.0, 3],
            ["customer", "Internal Customer satisfaction", "Satisfaction index", "0.9", 20.0, 4],
            ["internal_process", "Improve delivery period - clients", "time taken", "<24 hours", 20.0, 5],
            ["internal_process", "Standardization of Processess", "Compliance Levels", "By 30th June", 7.0, 6],
            ["internal_process", "Ensure cleanliness of delivery vans", "Cleansliness level", "1", 7.0, 7],
            ["internal_process", "Timely collection / dispatch of deliveries", "Timeline", "Within 8hrs", 6.0, 8],
            ["learning_growth", "Staff Training", "% of Staff Trained", "0.5", 4.0, 9],
            ["learning_growth", "Observe company core values", "Core value index", "0.9", 3.0, 10],
            ["learning_growth", "Inculcate Performance based culture", "Timely submission of duly filled BSC form for analysis", "By 3rd of the following month", 3.0, 11],
            ["learning_growth", "1=Not met;", "4 = Generally exceeding;", "5= Exceeded by far", 0, 12],
            ["learning_growth", "musinguzi geofrey", "2015-02-04 00:00:00", null, 0, 13],
            ["learning_growth", "Signed……………………………", "Date…………………….", null, 0, 14],
            ],
        );

        $this->template(
            name: "Sales Executive",
            jobTitle: "Sales Executive",
            description: "From the Sales Executive scorecard in BSC - HRM - 2024.xlsx. Source sheet totals 96.5%, not 100%. learning_growth header 15% vs KPIs 19.5%.",
            // Derived from the KPIs beneath each perspective rather than the
            // sheet's printed header, so the template is internally consistent.
            weights: ['financial' => 42.0, 'customer' => 20.0, 'internal_process' => 15.0, 'learning_growth' => 19.5],
            isActive: false,
            kpis: [
            ["financial", "Increase Company Sales Volume", "Value of sales - Annually", "200M", 20.0, 1],
            ["financial", "Account Development and Growth", "20 Net new accounts - Annually", "5 A/cs", 12.0, 2],
            ["financial", "Participate through proper documention, bidding process and share timely proposals and or quotations", "Submit timely proposals to clients", "30 Mins to Time", 10.0, 3],
            ["customer", "Customer relationship Management ( Int& Ext)", "No. of training programs - Quarterly", "3 Trainings", 5.0, 4],
            ["customer", "Innovative New products and services", "No. of New Services Introducted - Anuually", "1 Service", 3.0, 5],
            ["customer", "Make follow ups with customers and maintain business relations", "% Accounts retained - Annually", "0.05", 3.0, 6],
            ["customer", "Roll out quantitative and qualitative client satisfaction survery's, analyse and share survey results", "100% Roll out of satsifaction surveys, analyse and share results - Quarterly", "50 Surveys", 4.0, 7],
            ["customer", "Customer Satisfaction Index", "Attainment of a 95% customer satisfaction rating based on external Survey results", "0.95", 5.0, 8],
            ["internal_process", "Timely submission of accurate Daily sales reports", "Submit reports weekly", "Daily", 2.0, 9],
            ["internal_process", "Maximise business promotions for Brand affinity", "Promotional Methods Introduced", "Methods", 3.0, 10],
            ["internal_process", "Adhere to ISO standards whilst implementing & executing plans", "Process Compliance Levels", "1", 2.0, 11],
            ["internal_process", "Monthly performance review of direct reports performance", "Conduct monthly one-on-one Performance reviews with staff by the 20th of the next month", "1", 2.0, 12],
            ["internal_process", "Monthly performance reports.", "Submit reports by the 6th of every Month", "1", 3.0, 13],
            ["internal_process", "Competitor analysis", "Generation of bi-annual competitor analysis reports highlighting findings and ensure 60% implementation of action plans based on the identified insights.", "Twice a year", 3.0, 14],
            ["learning_growth", "Staff Training and development", "100% of Trained staff in department by Q4", "1", 3.0, 15],
            ["learning_growth", "Staff improvement plans (PIP's) from BSC's", "Ensure follow up and 100% completion of BSC action plans indicated in the PIPs for self by Q4 and uptake at least one training session by Q3", "1", 2.5, 16],
            ["learning_growth", "Innovation and creativity in work processes standards", "1 New standards implemented successfully by Q3", "1", 3.0, 17],
            ["learning_growth", "Regular staff meetings", "Minimum weekly meetings", "1", 3.0, 18],
            ["learning_growth", "5. COMPANY VALUES - 8%", "PERFORMANCE MEASURES", "TARGET", 0, 19],
            ["learning_growth", "Integrity", "Level Integrity Staff Exhibit", "1", 2.0, 20],
            ["learning_growth", "Team Work", "Ability to Achieve as a Group", "1", 2.0, 21],
            ["learning_growth", "Respect", "Respect for One Another", "1", 2.0, 22],
            ["learning_growth", "Innovation", "Ability to Think out of the Box", "1", 2.0, 23],
            ["learning_growth", "1 Not met;", "4 = Generally exceeding;", "5 = Exceeded by far", 0, 24],
            ],
        );

        $this->template(
            name: "Bid Compiler",
            jobTitle: "Bid Compiler",
            description: "From the Bid Compilers scorecard in BSC - HRM - 2024.xlsx. Source sheet totals 79.5%, not 100%. customer header 20% vs KPIs 8.0%; internal_process header 15% vs KPIs 10.0%; learning_growth header 15% vs KPIs 19.5%.",
            // Derived from the KPIs beneath each perspective rather than the
            // sheet's printed header, so the template is internally consistent.
            weights: ['financial' => 42.0, 'customer' => 8.0, 'internal_process' => 10.0, 'learning_growth' => 19.5],
            isActive: false,
            kpis: [
            ["financial", "Increase No. of Bids Handled in Time", "Timely Compilations of Bids", "1", 20.0, 1],
            ["financial", "Improve the Documentation Process", "Safely Keep Bid / Document Attachments", "1", 12.0, 2],
            ["financial", "Proper Understanding and Compilation of Bids in Accordance to ITBs", "Very Documents before Submission", "1", 10.0, 3],
            ["customer", "Understanding and Replying to Clarification Requests from Custmers", "No. of training programs - Quarterly", "3 Trainings", 5.0, 4],
            ["customer", "Innovative Bid Handling Ideas", "No. of New Services Introducted - Anuually", "1 Service", 3.0, 5],
            ["internal_process", "Timely submission of accurate Daily sales reports", "Submit reports weekly", "Daily", 2.0, 6],
            ["internal_process", "Maximise business promotions for Brand affinity", "Promotional Methods Introduced", "Methods", 3.0, 7],
            ["internal_process", "Adhere to ISO standards whilst implementing & executing plans", "Process Compliance Levels", "1", 2.0, 8],
            ["internal_process", "Monthly performance reports.", "Submit reports by the 6th of every Month", "1", 3.0, 9],
            ["learning_growth", "Staff Training and development", "100% of Trained staff in department by Q4", "1", 3.0, 10],
            ["learning_growth", "Staff improvement plans (PIP's) from BSC's", "Ensure follow up and 100% completion of BSC action plans indicated in the PIPs for self by Q4 and uptake at least one training session by Q3", "1", 2.5, 11],
            ["learning_growth", "Innovation and creativity in work processes standards", "1 New standards implemented successfully by Q3", "1", 3.0, 12],
            ["learning_growth", "Regular staff meetings", "Minimum weekly meetings", "1", 3.0, 13],
            ["learning_growth", "5. COMPANY VALUES - 8%", "PERFORMANCE MEASURES", "TARGET", 0, 14],
            ["learning_growth", "Integrity", "Level Integrity Staff Exhibit", "1", 2.0, 15],
            ["learning_growth", "Team Work", "Ability to Achieve as a Group", "1", 2.0, 16],
            ["learning_growth", "Respect", "Respect for One Another", "1", 2.0, 17],
            ["learning_growth", "Innovation", "Ability to Think out of the Box", "1", 2.0, 18],
            ["learning_growth", "1 Not met;", "4 = Generally exceeding;", "5 = Exceeded by far", 0, 19],
            ],
        );
    }

    /**
     * One template and its KPIs, written so re-running corrects rather than duplicates.
     *
     * @param  array<string, float>  $weights
     * @param  array<int, array{0:string,1:string,2:?string,3:?string,4:float,5:int}>  $kpis
     */
    private function template(
        string $name,
        string $jobTitle,
        string $description,
        array $weights,
        bool $isActive,
        array $kpis,
    ): void {
        $template = AppraisalTemplate::firstOrNew(['name' => $name]);

        $template->fill([
            'job_title' => $jobTitle,
            'description' => $description,
            'financial_weight' => $weights['financial'],
            'customer_weight' => $weights['customer'],
            'internal_process_weight' => $weights['internal_process'],
            'learning_growth_weight' => $weights['learning_growth'],
            'is_active' => $isActive,
        ])->save();

        // Replaced wholesale rather than merged: these come from one spreadsheet,
        // and a half-updated card is worse than either version of it.
        $template->kpis()->delete();

        foreach ($kpis as [$perspective, $kra, $measure, $target, $weight, $order]) {
            AppraisalTemplateKpi::create([
                'appraisal_template_id' => $template->id,
                'perspective' => $perspective,
                'kra_name' => $kra,
                'performance_measure' => $measure,
                'target' => $target,
                'weightage' => $weight,
                'sort_order' => $order,
            ]);
        }

        $this->command?->info(sprintf(
            '%s: %d KPIs, %s%s',
            $name,
            count($kpis),
            $isActive ? 'active' : 'INACTIVE - weights do not total 100%',
            PHP_EOL === "
" ? '' : '',
        ));
    }
}
