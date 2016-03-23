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

var populationPercentAfterSelection = 0.2; // Proportion (percentage) of the population to keep after selection.
var fitnessPreference = 0.9; // Preference for keeping fit individuals in the population. E.g. 0.8 means 80% of remaining population will be from fittest, 20% randomly from the rest.
var mutationRate = 0.05; // Chance of mutation.

var populationSize = 0; // Number of individuals in population.
var populationSizeAfterSelection = 0; // Number of individuals to keep after selection, forming the parents of the next generation.
var numberOfGenerations = 0; // Number of generations to iterate through.
var pluginCacheTTL = 0; // Original plugin caching setting; for restoring.
var courseId = 0; // Moodle course id.
var targetGradeItemId = 0; // Moodle grade item id of the target variable.

var geneSettings = {}; // Stores the settings (e.g. min, max) for each possible gene.
var currentPopulation = []; // Stores the current population.
var currentGenerationNumber = 0; // Stores which generation we are up to.
var fitnessHistory = []; // 0-based array storing average fitness of each generation.

$(document).ready(function(){
	$("input:submit[name=submitrundiscovery]").on("click", function(event, ui){
		event.preventDefault();
		// Set variables.
		courseId = $("input:hidden[name=id]").val();
		targetGradeItemId = $("select[name=target]").val();
		populationSize = $("select[name=iteri]").val();
		populationSizeAfterSelection = Math.floor(populationSize * populationPercentAfterSelection);
		numberOfGenerations = $("select[name=iterj]").val();
		// Kick off.
		$.get(
			"indicator_helper.ajax.php?id=" + courseId + "&method=initialise"
		).done(function(data){
			pluginCacheTTL = data;
			runGeneticAlgorithm();
		});
		return false;
	});
});

function runGeneticAlgorithm(){
	// Get possible settings for selected indicator.
	$.get(
		"indicator_helper.ajax.php?id=" + courseId + "&method=get_possible_settings&indicator=" + $("select[name=indicator]").val()
	).done(function(data){
		$("#output").html('');
		// Parse gene settings.
		geneSettings = JSON.parse(data);
		$("#output").append("<div>" + Object.keys(geneSettings).length + " genes</div>");
		// Make initial generation.
		initialGeneration = makeInitialGeneration(geneSettings);
		// Iterate through generations.
		currentPopulation = initialGeneration;
		currentGenerationNumber = 1;
		makeNextGeneration();
		algorithmRunner();
	});
	/*
	*/
}

function algorithmRunner() {
	if ($.ajaxq.isRunning('generation' + currentGenerationNumber)) {
		// Keep waiting.
		setTimeout(algorithmRunner, 500);
	} else {
		// Check to see if fitnesses are all calculated.
		for (var i = 0; i < currentPopulation.length; i++) {
			if (currentPopulation[i]["fitness"] == -1) {
				// Keep waiting.
				setTimeout(algorithmRunner, 500);
				break;
			}
		}
		// Probably finished with this generation...
		averageFitness = calculateCurrentPopulationAverageFitness();
		// See if we are moving anywhere.
		if (fitnessHistory.length > 4) {
			rollingAverageFitness = arrayAverage(fitnessHistory.slice(-4));
			if ((Math.abs(rollingAverageFitness - averageFitness) / averageFitness < 0.01) || (rollingAverageFitness == 0)) {
				// Have probably found the optimum or gotten stuck.
				$("#output").append("<div>Terminating algorithm early due to lack of progress.</div>");
				algorithmFinished();
				return;
			}
		}
		fitnessHistory.push(averageFitness);
		console.log("current generation " + currentGenerationNumber + " average fitness " + averageFitness);
		$("#output").append("<div>Generation " + currentGenerationNumber + " average fitness " + averageFitness + "</div>");
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

function algorithmFinished() {
	console.log('finished');
	currentPopulation.sort(compareFitness).reverse();
	//$("#output").append("<div>Best individual:<pre>" + JSON.stringify(currentPopulation[0]) + "</pre></div>");
	// Final calculation and save settings.
	$.get("indicator_helper.ajax.php?id=" + courseId 
		+ "&method=try_settings&indicator=" + $("select[name=indicator]").val() 
		+ "&targetgradeitemid=" + targetGradeItemId
		+ "&settings=" + encodeURI(JSON.stringify(currentPopulation[0]["genetics"])) 
	).done(function(data) {
		data = JSON.parse(data);
		$("#output").append("<div>Best fitness: " + data["fitness"] + "</div>");
		$("#output").append("<div>Finished. Best settings have been saved.</div>");
	});
	// Other server-side finalisation steps.
	$.get(
		"indicator_helper.ajax.php?id=" + courseId + "&method=finalise&plugincacheconfig=" + pluginCacheTTL
	);
}

function calculateCurrentPopulationAverageFitness() {
	var fitnessSum = 0;
	for (i = 0; i < currentPopulation.length; i++) {
		fitnessSum += currentPopulation[i]["fitness"];
	}
	return fitnessSum / currentPopulation.length;
}

function makeInitialGeneration(genes) {
	var population = [];
	for (i = 1; i <= populationSize; i++) {
		population.push(makeNewIndividual(genes));
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
		if (currentPopulation[i]["fitness"] == -1) { // Only calculate fitness if necessary.
			console.log("calculating fitness for generation [" + currentGenerationNumber + "] individual [" + i + "]");
			var returnData = {'i':i};
			// Push to server for calculation.
			$.ajaxq(
				"generation" + currentGenerationNumber, 
				{
					url: "indicator_helper.ajax.php?id=" + courseId 
						+ "&method=try_settings&indicator=" + $("select[name=indicator]").val() 
						+ "&targetgradeitemid=" + targetGradeItemId
						+ "&settings=" + encodeURI(JSON.stringify(currentPopulation[i]["genetics"])) 
						+ "&returndata=" + encodeURI(JSON.stringify(returnData)),
					type: 'GET'
				}
			).done(function(data){
				data = JSON.parse(data);
				currentPopulation[data["returndata"]["i"]]["fitness"] = data["fitness"];
				console.log('DONE on individual ' + data["returndata"]["i"] + ' in generation ' + currentGenerationNumber + ', fitness ' + data["fitness"]);
			}).fail(function() {
				console.log('FAIL on individual ' + data["returndata"]["i"] + ' in generation ' + currentGenerationNumber);
			});
		} else {
			// Fitness already known.
			console.log("fitness for generation [" + currentGenerationNumber + "] individual [" + i + "] already known: " + currentPopulation[i]["fitness"]);
		}
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
					console.log("mutating individual " + i + " gene " + gene);
					currentPopulation[i]["genetics"][gene] = randomFromInterval(genes[gene]["min"], genes[gene]["max"]);
				}
			}
		}
	}
}

function compareFitness(individual1, individual2) {
	if (individual1["fitness"] < individual2["fitness"]) {
		return -1;
	} else if (individual1["fitness"] > individual2["fitness"]) {
		return 1;
	} else {
		return 0;
	}
}

function makeNewIndividual(genes) {
	var individual = {};
	var genetics = {};
	for (var gene in genes) {
		if (genes.hasOwnProperty(gene)) {
			genetics[gene] = randomFromInterval(genes[gene]["min"], genes[gene]["max"]);
		}
	}
	individual["genetics"] = genetics;
	individual["fitness"] = -1;
	return individual;
}

function crossIndividuals(parent1, parent2, genes) {
	var newIndividual = {};
	var newGenetics = {};
	// For each gene, randomly pick which parent the allele comes from.
	for (var gene in genes) {
		if (genes.hasOwnProperty(gene)) {
			if (Math.random() < 0.5) {
				newGenetics[gene] = parent1["genetics"][gene];
			} else {
				newGenetics[gene] = parent2["genetics"][gene];
			}
		}
	}
	// Return new individual.
	newIndividual["genetics"] = newGenetics;
	newIndividual["fitness"] = -1;
	return newIndividual;
}

function arrayAverage(arr) {
	var arrSum = 0;
	for (var i = 0; i < arr.length; i++) {
		arrSum += arr[i];
	}
	return arrSum / arr.length;
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