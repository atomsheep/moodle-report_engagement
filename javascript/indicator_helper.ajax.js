// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/*
 * Genetic algorithm implemented in Javascript.
 *
 * @package    report_engagement
 * @author     Danny Liu <danny.liu@mq.edu.au>
 * @copyright  2016 Macquarie University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

var populationPercentAfterSelection = 0.4; // Proportion (percentage) of the population to keep after selection.
var fitnessPreference = 0.6; // Preference for keeping fit individuals in the population. E.g. 0.8 means 80% of remaining population will be from fittest, 20% randomly from the rest.
var mutationRate = 0.1; // Chance of mutation.

var populationSize = 0; // Number of individuals in population.
var populationSizeAfterSelection = 0; // Number of individuals to keep after selection, forming the parents of the next generation.
var numberOfGenerations = 0; // Number of generations to iterate through.
var pluginCacheTTL = 0; // Original plugin caching setting; for restoring.
var courseIds = []; // Moodle course ids.
var courseNames = []; // Course shortnames.
var targetGradeItemIds = []; // Moodle grade item id of the target variable.

var geneSettings = {}; // Stores the settings (e.g. min, max) for each possible gene.
var discoverableIndicators = []; // String names of the discoverable indicators we are working with.
var currentPopulation = []; // Stores the current population.
var currentGenerationNumber = 0; // Stores which generation we are up to.
var fitnessHistory = {'overall':[], 'elite':[]}; // 0-based arrays storing average fitness of each generation.

$(document).ready(function() {
	$("input:submit[name=submitrundiscovery]").on("click", function(event, ui){
		event.preventDefault();
		// Set variables.
		$("select[name^=target_]").each(function(){
			//console.log($(this).attr('name'));
			var courseId = $(this).attr('name').replace('target_', '');
			courseIds.push( courseId );
			targetGradeItemIds.push( $("select[name=target_" + courseId + "]").val() );
			courseNames.push( $("input:hidden[name=coursename_" + courseId + "]").val() );
		});
		populationSize = $("select[name=iteri]").val();
		populationSizeAfterSelection = Math.floor(populationSize * populationPercentAfterSelection);
		numberOfGenerations = $("select[name=iterj]").val();
		// Kick off.
		$.get(
			"indicator_helper.ajax.php?id=" + courseIds[0] + "&method=initialise"
		).done(function(data){
			pluginCacheTTL = data;
			runGeneticAlgorithm();
		});
		return false;
	});
});

function runGeneticAlgorithm() {
	// Get possible settings for selected indicator.
	$.get(
		"indicator_helper.ajax.php?id=" + courseIds[0] + "&method=get_possible_settings&indicator=" + $("select[name=indicator]").val()
	).done(function(data){
		$("#output").html('');
		// Parse settings and store in global variables.
		data = JSON.parse(data);
		geneSettings = data["settings"];
		discoverableIndicators = data["indicators"];
		algorithmStarted();
		// Make initial generation.
		initialGeneration = makeInitialGeneration(geneSettings);
		// Iterate through generations.
		currentPopulation = initialGeneration;
		currentGenerationNumber = 1;
		//makeNextGeneration();
		calculateCurrentPopulationFitness();
		algorithmRunner();
	});
}

function algorithmRunner() {
	if ($.ajaxq.isRunning('generation' + currentGenerationNumber)) {
		// Keep waiting.
		setTimeout(algorithmRunner, 500);
	} else {
		// Check to see if fitnesses are all calculated.
		for (var i = 0; i < currentPopulation.length; i++) {
			for (var c = 0; c < courseIds.length; c++) {
				if (currentPopulation[i]["fitness"]["course" + courseIds[c]] == -1) {
					// Keep waiting.
					setTimeout(algorithmRunner, 500);
					break;
				}
			}
		}
		// Probably finished with this generation...
		averageFitness = calculateCurrentPopulationAverageFitness();
		averageEliteFitness = calculateCurrentPopulationAverageFitness(fitnessPreference / 5); // Average fitness of the most elite.
		// See if we are moving anywhere.
		if (fitnessHistory['elite'].length > 4) {
			rollingAverageFitness = arrayAverage(fitnessHistory['elite'].slice(-4));
			if ((Math.abs(rollingAverageFitness - averageEliteFitness) / averageEliteFitness < 0.01) || (rollingAverageFitness == 0)) {
				// Have probably found the optimum or gotten stuck.
				$("#output").append("<div>Terminating algorithm early due to lack of improvement in fitness.</div>");
				algorithmFinished();
				return;
			}
		}
		fitnessHistory['overall'].push(averageFitness);
		fitnessHistory['elite'].push(averageEliteFitness);
		//console.log("current generation " + currentGenerationNumber + " average fitness " + averageFitness);
		$("#output").append("<div>Generation " + currentGenerationNumber + " average fitness: overall " + averageFitness + ", elite " + averageEliteFitness + "</div>");
		// Iterate to next generation.
		currentGenerationNumber++;
		if (currentGenerationNumber <= numberOfGenerations) {
			makeNextGeneration();
			algorithmRunner();
		} else {
			// Finished!
			algorithmFinished();
		}
	}
}

function algorithmStarted() {
	//console.log(geneSettings, discoverableIndicators);
	$("#output").append("<div>" + Object.keys(geneSettings).length + " genes</div>");
	$("#progress-bar").attr('max', populationSize * numberOfGenerations * courseIds.length);
	$("#output-container").show();
}

function algorithmFinished() {
	//console.log('finished');
	currentPopulation.sort(compareFitness).reverse();
	// Final calculation and save settings.
	for (c = 0; c < courseIds.length; c++) {
		var returnData = {'c':courseIds[c], 'cn':courseNames[c]};
		$.ajaxq(
			"final", 
			{
				url: "indicator_helper.ajax.php?id=" + courseIds[c]
					+ "&method=try_settings" 
					+ "&targetgradeitemid=" + targetGradeItemIds[c]
					+ "&settings=" + encodeURI(JSON.stringify(currentPopulation[0]["genotype"])) 
					+ "&returndata=" + encodeURI(JSON.stringify(returnData)),
				type: 'GET'
			}
		).done(function(data){
			data = JSON.parse(data);
			$("#output").append("<div>Best fitness for course " + data["returndata"]["cn"] + ": " + data["fitness"] + ". Finished with this course; best settings have been saved.</div>");
			$("#output-progress").hide();
		});
	}
	// Other server-side finalisation steps.
	$.get(
		"indicator_helper.ajax.php?id=" + courseIds[0] + "&method=finalise&plugincacheconfig=" + pluginCacheTTL
	);
}

function incrementProgressBar(value = 1) {
	$("#progress-bar").attr('value', parseInt($("#progress-bar").attr('value')) + value);
}

function calculateCurrentPopulationAverageFitness(elitePortion = 1) {
	var fitnessSum = 0.0;
	// Sort currentPopulation by fitness.
	currentPopulation.sort(compareFitness).reverse();
	// Work out which proportion of top (elite) to calculate average fitness for.
	var calculateUntil = Math.floor(currentPopulation.length * (elitePortion));
	// Calculate average fitness.
	for (i = 0; i < calculateUntil; i++) {
		for (c = 0; c < courseIds.length; c++) {
			fitnessSum += currentPopulation[i]["fitness"]["course" + courseIds[c]];
		}
	}
	return fitnessSum / (calculateUntil * courseIds.length);
}

function makeInitialGeneration(genes) {
	var population = [];
	for (i = 1; i <= populationSize; i++) {
		// Randomise genotype for new individual, within specified bounds for each gene.
		var newIndividual = {};
		var newGenotype = {};
		for (var gene in genes) {
			if (genes.hasOwnProperty(gene)) {
				newGenotype[gene] = randomFromInterval(genes[gene]["min"], genes[gene]["max"]);
			}
		}
		newIndividual["genotype"] = newGenotype;
		newIndividual["fitness"] = newIndividualFitnessObject();
		// Add new individual to population.
		population.push(newIndividual);
	}
	return population;
}

function makeNextGeneration() {
	// Natural selection: eliminate less fit individuals (but keeping a proportion of the less fit).
	var remainingPopulation = naturalSelection();
	// Breeding: make new individuals from remaining population.
	currentPopulation = breedPopulation(remainingPopulation, geneSettings);
	// Mutation: introduce random mutations.
	mutateCurrentPopulation(mutationRate, geneSettings);
	// Calculate fitness of individuals in the new (i.e. now current) population.
	calculateCurrentPopulationFitness();
	// Exit.
	return true;
}

function calculateCurrentPopulationFitness() {
	for (var i = 0; i < currentPopulation.length; i++) {
		// Calculate fitness for each individual in each course.
		for (var c = 0; c < courseIds.length; c++) {
			if (currentPopulation[i]["fitness"]["course" + courseIds[c]] == -1) { // Only calculate fitness if necessary.
				console.log("requesting from server: fitness for generation " + currentGenerationNumber + " individual " + i + " course " + c);
				var returnData = {'i':i, 'c':courseIds[c]};
				// Push to server for calculation.
				$.ajaxq(
					"generation" + currentGenerationNumber, 
					{
						url: "indicator_helper.ajax.php?id=" + courseIds[c]
							+ "&method=try_settings" 
							+ "&targetgradeitemid=" + targetGradeItemIds[c]
							+ "&settings=" + encodeURI(JSON.stringify(currentPopulation[i]["genotype"])) 
							+ "&returndata=" + encodeURI(JSON.stringify(returnData)),
						type: 'GET'
					}
				).done(function(data){
					data = JSON.parse(data);
					currentPopulation[data["returndata"]["i"]]["fitness"]["course" + data["returndata"]["c"]] = data["fitness"];
					//console.log('DONE on individual ' + data["returndata"]["i"] + ' in generation ' + currentGenerationNumber + ', fitness ' + data["fitness"]);
					incrementProgressBar();
				}).fail(function() {
					//console.log('FAIL on individual ' + data["returndata"]["i"] + ' in generation ' + currentGenerationNumber);
				});
			} else {
				// Fitness already known.
				//console.log("fitness for generation [" + currentGenerationNumber + "] individual [" + i + "] already known: " + currentPopulation[i]["fitness"]);
			}
		}
		// Then average this individual's fitnesses across all tested courses.
		currentPopulation[i]["fitness"]["_overall"] = objectArrayAverage(currentPopulation[i]["fitness"], "_");
	}
}

function naturalSelection() {
	// Calculate the fitness for each individual in the current population.
	calculateCurrentPopulationFitness();
	// Sort according to fitness.
	var tempPopulation = currentPopulation.slice();
	tempPopulation.sort(compareFitness).reverse();
	// Keep some of the population - fittest plus some randoms.
	var numberOfFittest = Math.floor(populationSizeAfterSelection * fitnessPreference);
	var numberOfTheRest = populationSizeAfterSelection - numberOfFittest;
	var remainingPopulation = [];
	for (var i = 1; i <= numberOfFittest; i++) { // Keeping the fittest.
		remainingPopulation.push(tempPopulation.shift());
	}
	for (var i = 1; i <= numberOfTheRest; i++) { // Choose some from remaining population at random.
		shuffle(tempPopulation);
		remainingPopulation.push(tempPopulation.shift());
	}
	// Return.
	return remainingPopulation;
}

function breedPopulation(population, genes) {
	var newPopulation = [];
	for (i = 1; i <= populationSize - populationSizeAfterSelection; i++) {
		shuffle(population);
		var parent1Index = Math.floor(randomFromInterval(0, population.length - 1));
		var parent2Index = parent1Index;
		while (parent1Index == parent2Index) {
			parent2Index = Math.floor(randomFromInterval(0, population.length - 1));
		}
		var newIndividual = crossIndividuals(
			population[parent1Index],
			population[parent2Index],
			genes
		);
		newPopulation.push(newIndividual);
	}
	newPopulation = newPopulation.concat(population);
	return newPopulation;
}

function mutateCurrentPopulation(mutationRate, genes) {
	for (var i = 0; i < currentPopulation.length; i++) {
		for (var gene in genes) {
			if (genes.hasOwnProperty(gene)) {
				if (Math.random() < mutationRate) {
					//console.log("mutating individual " + i + " gene " + gene);
					currentPopulation[i]["genotype"][gene] = randomFromInterval(genes[gene]["min"], genes[gene]["max"]);
				}
			}
		}
	}
}

function compareFitness(individual1, individual2) {
	if (individual1["fitness"]["_overall"] < individual2["fitness"]["_overall"]) {
		return -1;
	} else if (individual1["fitness"]["_overall"] > individual2["fitness"]["_overall"]) {
		return 1;
	} else {
		return 0;
	}
}

function crossIndividuals(parent1, parent2, genes) {
	var newIndividual = {};
	var newGenetics = {};
	// For each gene, randomly pick which parent the allele comes from.
	for (var gene in genes) {
		if (genes.hasOwnProperty(gene)) {
			if (Math.random() < 0.5) {
				newGenetics[gene] = parent1["genotype"][gene];
			} else {
				newGenetics[gene] = parent2["genotype"][gene];
			}
		}
	}
	// Return new individual.
	newIndividual["genotype"] = newGenetics;
	newIndividual["fitness"] = newIndividualFitnessObject();
	return newIndividual;
}

function newIndividualFitnessObject() {
	var individualFitness = {};
	individualFitness["_overall"] = -1;
	for (c = 0; c < courseIds.length; c++) {
		individualFitness["course" + courseIds[c]] = -1;
	}
	return individualFitness;
}

function arrayAverage(arr) {
	var arrSum = 0;
	for (var i = 0; i < arr.length; i++) {
		arrSum += arr[i];
	}
	return arrSum / arr.length;
}

function objectArrayAverage(objArr, ignoreIfKeyStartsWith) {
	var arrSum = 0;
	var arrLength = 0;
	for (var key in objArr) {
		if (!objArr.hasOwnProperty(key)) continue;
		if (typeof ignoreIfKeyStartsWith !== 'undefined') {
			if (key.startsWith(ignoreIfKeyStartsWith)) continue;
		}
		arrSum += objArr[key];
		arrLength++;
	}
	return arrSum / arrLength;
}

function randomFromInterval(min, max)
{
	return (Math.random() * (max - min + 1) + min);
}        

function shuffle(array) {
  // http://stackoverflow.com/questions/2450954/how-to-randomize-shuffle-a-javascript-array
  var currentIndex = array.length, temporaryValue, randomIndex;
  // While there remain elements to shuffle...
  while (0 !== currentIndex) {
    // Pick a remaining element...
    randomIndex = Math.floor(Math.random() * currentIndex);
    currentIndex -= 1;
    // And swap it with the current element.
    temporaryValue = array[currentIndex];
    array[currentIndex] = array[randomIndex];
    array[randomIndex] = temporaryValue;
  }
  return array;
}