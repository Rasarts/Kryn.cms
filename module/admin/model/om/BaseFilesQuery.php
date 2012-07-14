<?php


/**
 * Base class that represents a query for the 'kryn_system_files' table.
 *
 * 
 *
 * @method     FilesQuery orderById($order = Criteria::ASC) Order by the id column
 * @method     FilesQuery orderByPath($order = Criteria::ASC) Order by the path column
 * @method     FilesQuery orderByContenthash($order = Criteria::ASC) Order by the contenthash column
 *
 * @method     FilesQuery groupById() Group by the id column
 * @method     FilesQuery groupByPath() Group by the path column
 * @method     FilesQuery groupByContenthash() Group by the contenthash column
 *
 * @method     FilesQuery leftJoin($relation) Adds a LEFT JOIN clause to the query
 * @method     FilesQuery rightJoin($relation) Adds a RIGHT JOIN clause to the query
 * @method     FilesQuery innerJoin($relation) Adds a INNER JOIN clause to the query
 *
 * @method     Files findOne(PropelPDO $con = null) Return the first Files matching the query
 * @method     Files findOneOrCreate(PropelPDO $con = null) Return the first Files matching the query, or a new Files object populated from the query conditions when no match is found
 *
 * @method     Files findOneById(int $id) Return the first Files filtered by the id column
 * @method     Files findOneByPath(string $path) Return the first Files filtered by the path column
 * @method     Files findOneByContenthash(string $contenthash) Return the first Files filtered by the contenthash column
 *
 * @method     array findById(int $id) Return Files objects filtered by the id column
 * @method     array findByPath(string $path) Return Files objects filtered by the path column
 * @method     array findByContenthash(string $contenthash) Return Files objects filtered by the contenthash column
 *
 * @package    propel.generator.Kryn.om
 */
abstract class BaseFilesQuery extends ModelCriteria
{
    
    /**
     * Initializes internal state of BaseFilesQuery object.
     *
     * @param     string $dbName The dabase name
     * @param     string $modelName The phpName of a model, e.g. 'Book'
     * @param     string $modelAlias The alias for the model in this query, e.g. 'b'
     */
    public function __construct($dbName = 'Kryn', $modelName = 'Files', $modelAlias = null)
    {
        parent::__construct($dbName, $modelName, $modelAlias);
    }

    /**
     * Returns a new FilesQuery object.
     *
     * @param     string $modelAlias The alias of a model in the query
     * @param     FilesQuery|Criteria $criteria Optional Criteria to build the query from
     *
     * @return FilesQuery
     */
    public static function create($modelAlias = null, $criteria = null)
    {
        if ($criteria instanceof FilesQuery) {
            return $criteria;
        }
        $query = new FilesQuery();
        if (null !== $modelAlias) {
            $query->setModelAlias($modelAlias);
        }
        if ($criteria instanceof Criteria) {
            $query->mergeWith($criteria);
        }

        return $query;
    }

    /**
     * Find object by primary key.
     * Propel uses the instance pool to skip the database if the object exists.
     * Go fast if the query is untouched.
     *
     * <code>
     * $obj  = $c->findPk(12, $con);
     * </code>
     *
     * @param mixed $key Primary key to use for the query 
     * @param     PropelPDO $con an optional connection object
     *
     * @return   Files|Files[]|mixed the result, formatted by the current formatter
     */
    public function findPk($key, $con = null)
    {
        if ($key === null) {
            return null;
        }
        if ((null !== ($obj = FilesPeer::getInstanceFromPool((string) $key))) && !$this->formatter) {
            // the object is alredy in the instance pool
            return $obj;
        }
        if ($con === null) {
            $con = Propel::getConnection(FilesPeer::DATABASE_NAME, Propel::CONNECTION_READ);
        }
        $this->basePreSelect($con);
        if ($this->formatter || $this->modelAlias || $this->with || $this->select
         || $this->selectColumns || $this->asColumns || $this->selectModifiers
         || $this->map || $this->having || $this->joins) {
            return $this->findPkComplex($key, $con);
        } else {
            return $this->findPkSimple($key, $con);
        }
    }

    /**
     * Find object by primary key using raw SQL to go fast.
     * Bypass doSelect() and the object formatter by using generated code.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return   Files A model object, or null if the key is not found
     * @throws   PropelException
     */
    protected function findPkSimple($key, $con)
    {
        $sql = 'SELECT ID, PATH, CONTENTHASH FROM kryn_system_files WHERE ID = :p0';
        try {
            $stmt = $con->prepare($sql);
			$stmt->bindValue(':p0', $key, PDO::PARAM_INT);
            $stmt->execute();
        } catch (Exception $e) {
            Propel::log($e->getMessage(), Propel::LOG_ERR);
            throw new PropelException(sprintf('Unable to execute SELECT statement [%s]', $sql), $e);
        }
        $obj = null;
        if ($row = $stmt->fetch(PDO::FETCH_NUM)) {
            $obj = new Files();
            $obj->hydrate($row);
            FilesPeer::addInstanceToPool($obj, (string) $key);
        }
        $stmt->closeCursor();

        return $obj;
    }

    /**
     * Find object by primary key.
     *
     * @param     mixed $key Primary key to use for the query
     * @param     PropelPDO $con A connection object
     *
     * @return Files|Files[]|mixed the result, formatted by the current formatter
     */
    protected function findPkComplex($key, $con)
    {
        // As the query uses a PK condition, no limit(1) is necessary.
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKey($key)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->formatOne($stmt);
    }

    /**
     * Find objects by primary key
     * <code>
     * $objs = $c->findPks(array(12, 56, 832), $con);
     * </code>
     * @param     array $keys Primary keys to use for the query
     * @param     PropelPDO $con an optional connection object
     *
     * @return PropelObjectCollection|Files[]|mixed the list of results, formatted by the current formatter
     */
    public function findPks($keys, $con = null)
    {
        if ($con === null) {
            $con = Propel::getConnection($this->getDbName(), Propel::CONNECTION_READ);
        }
        $this->basePreSelect($con);
        $criteria = $this->isKeepQuery() ? clone $this : $this;
        $stmt = $criteria
            ->filterByPrimaryKeys($keys)
            ->doSelect($con);

        return $criteria->getFormatter()->init($criteria)->format($stmt);
    }

    /**
     * Filter the query by primary key
     *
     * @param     mixed $key Primary key to use for the query
     *
     * @return FilesQuery The current query, for fluid interface
     */
    public function filterByPrimaryKey($key)
    {

        return $this->addUsingAlias(FilesPeer::ID, $key, Criteria::EQUAL);
    }

    /**
     * Filter the query by a list of primary keys
     *
     * @param     array $keys The list of primary key to use for the query
     *
     * @return FilesQuery The current query, for fluid interface
     */
    public function filterByPrimaryKeys($keys)
    {

        return $this->addUsingAlias(FilesPeer::ID, $keys, Criteria::IN);
    }

    /**
     * Filter the query on the id column
     *
     * Example usage:
     * <code>
     * $query->filterById(1234); // WHERE id = 1234
     * $query->filterById(array(12, 34)); // WHERE id IN (12, 34)
     * $query->filterById(array('min' => 12)); // WHERE id > 12
     * </code>
     *
     * @param     mixed $id The value to use as filter.
     *              Use scalar values for equality.
     *              Use array values for in_array() equivalent.
     *              Use associative array('min' => $minValue, 'max' => $maxValue) for intervals.
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FilesQuery The current query, for fluid interface
     */
    public function filterById($id = null, $comparison = null)
    {
        if (is_array($id) && null === $comparison) {
            $comparison = Criteria::IN;
        }

        return $this->addUsingAlias(FilesPeer::ID, $id, $comparison);
    }

    /**
     * Filter the query on the path column
     *
     * Example usage:
     * <code>
     * $query->filterByPath('fooValue');   // WHERE path = 'fooValue'
     * $query->filterByPath('%fooValue%'); // WHERE path LIKE '%fooValue%'
     * </code>
     *
     * @param     string $path The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FilesQuery The current query, for fluid interface
     */
    public function filterByPath($path = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($path)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $path)) {
                $path = str_replace('*', '%', $path);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FilesPeer::PATH, $path, $comparison);
    }

    /**
     * Filter the query on the contenthash column
     *
     * Example usage:
     * <code>
     * $query->filterByContenthash('fooValue');   // WHERE contenthash = 'fooValue'
     * $query->filterByContenthash('%fooValue%'); // WHERE contenthash LIKE '%fooValue%'
     * </code>
     *
     * @param     string $contenthash The value to use as filter.
     *              Accepts wildcards (* and % trigger a LIKE)
     * @param     string $comparison Operator to use for the column comparison, defaults to Criteria::EQUAL
     *
     * @return FilesQuery The current query, for fluid interface
     */
    public function filterByContenthash($contenthash = null, $comparison = null)
    {
        if (null === $comparison) {
            if (is_array($contenthash)) {
                $comparison = Criteria::IN;
            } elseif (preg_match('/[\%\*]/', $contenthash)) {
                $contenthash = str_replace('*', '%', $contenthash);
                $comparison = Criteria::LIKE;
            }
        }

        return $this->addUsingAlias(FilesPeer::CONTENTHASH, $contenthash, $comparison);
    }

    /**
     * Exclude object from result
     *
     * @param   Files $files Object to remove from the list of results
     *
     * @return FilesQuery The current query, for fluid interface
     */
    public function prune($files = null)
    {
        if ($files) {
            $this->addUsingAlias(FilesPeer::ID, $files->getId(), Criteria::NOT_EQUAL);
        }

        return $this;
    }

} // BaseFilesQuery